<?php
header('Content-Type: application/json');

echo json_encode([
    'php_version' => phpversion(),
    'curl_loaded' => extension_loaded('curl'),
    'curl_version' => extension_loaded('curl') ? curl_version() : null,
    'test_results' => []
], JSON_PRETTY_PRINT);

// Test cURL functionality
if (extension_loaded('curl')) {
    echo "\n\n=== cURL Test Results ===\n";
    
    // Test 1: Basic cURL info
    $curl_info = curl_version();
    echo "cURL Version: " . $curl_info['version'] . "\n";
    echo "SSL Version: " . $curl_info['ssl_version'] . "\n";
    echo "Protocols: " . implode(', ', $curl_info['protocols']) . "\n";
    
    // Test 2: Make a simple HTTPS request to a test endpoint
    echo "\n--- Testing HTTPS Request ---\n";
    $ch = curl_init('https://httpbin.org/get');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP cURL Test');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL Error: " . $error . "\n";
    } else {
        echo "✅ HTTPS Request Successful (HTTP $http_code)\n";
        echo "Response length: " . strlen($response) . " bytes\n";
    }
    
    // Test 3: Test Resend API endpoint (without sending)
    echo "\n--- Testing Resend API Endpoint ---\n";
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP cURL Test');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Resend API Error: " . $error . "\n";
    } else {
        echo "✅ Resend API Reachable (HTTP $http_code)\n";
    }
    
} else {
    echo "\n❌ cURL extension is NOT loaded!\n";
    echo "You need to enable the cURL extension in your PHP configuration.\n";
}
?>
