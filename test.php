<?php

/**
 * Simple test file to verify deployment is working
 */

// Test basic PHP functionality
echo "<h2>✅ PHP is working!</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test environment variables
echo "<h3>Environment Variables Test:</h3>";
echo "<ul>";
echo "<li>APP_ENV: " . ($_ENV['APP_ENV'] ?? 'not set') . "</li>";
echo "<li>GEMINI_MODEL_NAME: " . ($_ENV['GEMINI_MODEL_NAME'] ?? 'not set') . "</li>";
echo "<li>GEMINI_API_KEY: " . (empty($_ENV['GEMINI_API_KEY']) ? 'not set' : 'set (hidden)') . "</li>";
echo "</ul>";

// Test curl extension
echo "<h3>Extensions Test:</h3>";
echo "<ul>";
echo "<li>curl: " . (extension_loaded('curl') ? '✅ Available' : '❌ Missing') . "</li>";
echo "<li>json: " . (extension_loaded('json') ? '✅ Available' : '❌ Missing') . "</li>";
echo "<li>openssl: " . (extension_loaded('openssl') ? '✅ Available' : '❌ Missing') . "</li>";
echo "</ul>";

// Test curl functionality
echo "<h3>cURL Test:</h3>";
if (function_exists('curl_version')) {
    $curlInfo = curl_version();
    echo "<p>✅ cURL Version: " . $curlInfo['version'] . "</p>";
    echo "<p>SSL Version: " . ($curlInfo['ssl_version'] ?? 'Not available') . "</p>";
} else {
    echo "<p>❌ cURL functions not available</p>";
}

// Test file permissions
echo "<h3>File System Test:</h3>";
echo "<ul>";
echo "<li>Current directory: " . getcwd() . "</li>";
echo "<li>index.php exists: " . (file_exists('index.php') ? '✅ Yes' : '❌ No') . "</li>";
echo "<li>config.php exists: " . (file_exists('config.php') ? '✅ Yes' : '❌ No') . "</li>";
echo "<li>Directory writable: " . (is_writable('.') ? '✅ Yes' : '❌ No') . "</li>";
echo "</ul>";

// Test simple HTTP request (if curl is available)
echo "<h3>Network Test:</h3>";
if (function_exists('curl_init')) {
    echo "<p>Testing HTTP connectivity...</p>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/ip');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpCode === 200) {
        echo "<p>✅ Network connectivity working</p>";
    } else {
        echo "<p>⚠️ Network test failed (HTTP Code: $httpCode)</p>";
    }
} else {
    echo "<p>❌ cURL not available for network test</p>";
}

echo "<hr>";
echo "<p><a href='/'>← Back to main app</a> | <a href='/index.html'>HTML Interface</a></p>";
