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
echo "</ul>";

// Test file permissions
echo "<h3>File System Test:</h3>";
echo "<ul>";
echo "<li>Current directory: " . getcwd() . "</li>";
echo "<li>index.php exists: " . (file_exists('index.php') ? '✅ Yes' : '❌ No') . "</li>";
echo "<li>config.php exists: " . (file_exists('config.php') ? '✅ Yes' : '❌ No') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='/'>← Back to main app</a> | <a href='/index.html'>HTML Interface</a></p>";
