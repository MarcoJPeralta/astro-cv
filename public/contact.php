<?php
header( 'Content-Type: application/json' );
session_start();

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	http_response_code( 405 );
	echo json_encode( array( 'error' => 'Method not allowed' ) );
	exit;
}

// Get client IP address
function get_client_ip() {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip_list = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip      = trim( $ip_list[0] );
	} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
		$ip = $_SERVER['HTTP_X_REAL_IP'];
	}
	return $ip;
}

// Rate limiting - Max 3 submissions per IP per hour
$client_ip       = get_client_ip();
$rate_limit_key  = 'contact_form_' . md5( $client_ip );
$max_submissions = 3;
$time_window     = 3600; // 1 hour in seconds

if ( ! isset( $_SESSION[ $rate_limit_key ] ) ) {
	$_SESSION[ $rate_limit_key ] = array();
}

// Clean old submissions
$_SESSION[ $rate_limit_key ] = array_filter(
	$_SESSION[ $rate_limit_key ],
	function ( $timestamp ) use ( $time_window ) {
		return ( time() - $timestamp ) < $time_window;
	}
);

// Check rate limit
if ( count( $_SESSION[ $rate_limit_key ] ) >= $max_submissions ) {
	http_response_code( 429 );
	echo json_encode( array( 'error' => 'Too many submissions. Please try again later.' ) );
	exit;
}

$name      = trim( $_POST['name'] ?? '' );
$email     = trim( $_POST['email'] ?? '' );
$company   = trim( $_POST['company'] ?? '' );
$subject   = trim( $_POST['subject'] ?? '' );
$message   = trim( $_POST['message'] ?? '' );
$honeypot  = trim( $_POST['website'] ?? '' );
$timestamp = $_POST['timestamp'] ?? '';

// Honeypot check - if filled, it's a bot
if ( ! empty( $honeypot ) ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Spam detected.' ) );
	exit;
}

// Time-based validation - form must be open for at least 3 seconds
if ( $timestamp ) {
	$time_elapsed = ( time() * 1000 ) - (int) $timestamp;
	if ( $time_elapsed < 3000 ) { // Less than 3 seconds
		http_response_code( 400 );
		echo json_encode( array( 'error' => 'Form submitted too quickly. Please try again.' ) );
		exit;
	}
	// Also check if timestamp is too old (more than 1 hour)
	if ( $time_elapsed > 3600000 ) { // More than 1 hour
		http_response_code( 400 );
		echo json_encode( array( 'error' => 'Form session expired. Please refresh and try again.' ) );
		exit;
	}
}

// Validate required fields
if ( ! $name || ! $email || ! $subject || ! $message ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Missing required fields. Please fill out all required fields.' ) );
	exit;
}

// Validate email format
if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Invalid email address.' ) );
	exit;
}

// Additional spam checks
// Check for suspicious patterns in message
$spam_patterns = array(
	'/\b(viagra|cialis|casino|lottery|prize)\b/i',
	'/http[s]?:\/\/.*http[s]?:\/\//i', // Multiple URLs
	'/<script/i', // Script tags
	'/<iframe/i', // Iframe tags
);

foreach ( $spam_patterns as $pattern ) {
	if ( preg_match( $pattern, $message ) || preg_match( $pattern, $subject ) ) {
		http_response_code( 400 );
		echo json_encode( array( 'error' => 'Your message contains prohibited content.' ) );
		exit;
	}
}

// Check message length (too short or suspiciously long)
if ( strlen( $message ) < 10 ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Message is too short. Please provide more details.' ) );
	exit;
}

if ( strlen( $message ) > 5000 ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'Message is too long. Please keep it under 5000 characters.' ) );
	exit;
}

// Resend API configuration
$apiKey = 're_aY4Y3KwX_46aJKF12ZiftuB3JZ3jL75i7';
$from   = 'Portfolio Contact <contact@mperalta.com>';
$to     = array( 'marko.jp.17@gmail.com', 'contact@mperalta.com' );

// Build HTML email
$companySection = $company ? "<p style='margin: 10px 0;'><strong>Company:</strong> " . htmlspecialchars( $company ) . '</p>' : '';

$html = "
<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
	<h2 style='color: #333; border-bottom: 2px solid #333; padding-bottom: 10px;'>
		New Contact Form Submission
	</h2>
	
	<div style='margin: 20px 0;'>
		<p style='margin: 10px 0;'>
			<strong>From:</strong> " . htmlspecialchars( $name ) . "
		</p>
		<p style='margin: 10px 0;'>
			<strong>Email:</strong> 
			<a href='mailto:" . htmlspecialchars( $email ) . "' style='color: #0066cc;'>" . htmlspecialchars( $email ) . "</a>
		</p>
		$companySection
		<p style='margin: 10px 0;'>
			<strong>Subject:</strong> " . htmlspecialchars( $subject ) . "
		</p>
	</div>
	
	<div style='background: #f5f5f5; border-left: 4px solid #333; padding: 15px; margin: 20px 0;'>
		<strong>Message:</strong>
		<p style='margin: 10px 0 0 0; white-space: pre-wrap;'>" . htmlspecialchars( $message ) . "</p>
	</div>
	
	<div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;'>
		<p>This email was sent from your portfolio contact form.</p>
	</div>
</div>";

// Prepare payload for Resend API
$payload = json_encode(
	array(
		'from'     => $from,
		'to'       => $to,
		'reply_to' => $email,
		'subject'  => 'Portfolio Contact: ' . $subject,
		'html'     => $html,
	)
);

// Send email via Resend API
$ch = curl_init( 'https://api.resend.com/emails' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt(
	$ch,
	CURLOPT_HTTPHEADER,
	array(
		'Authorization: Bearer ' . $apiKey,
		'Content-Type: application/json',
	)
);
curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );

$response   = curl_exec( $ch );
$status     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$curl_error = curl_error( $ch );
curl_close( $ch );

// Handle response
if ( $status >= 200 && $status < 300 ) {
	// Add timestamp to rate limit tracker
	$_SESSION[ $rate_limit_key ][] = time();

	http_response_code( 200 );
	echo json_encode(
		array(
			'message' => 'Message sent successfully! I will get back to you soon.',
			'data'    => json_decode( $response ),
		)
	);
} else {
	http_response_code( 500 );
	$error_message = $curl_error ? $curl_error : $response;
	echo json_encode(
		array(
			'error' => 'Failed to send email. Please try again or contact me directly.',
			'debug' => $error_message,
		)
	);
}
