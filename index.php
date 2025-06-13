<?php

/**
 * Production-Ready Gemini AI Chatbot
 * Optimized for deployment on free hosting platforms (Railway, Render, Fly.io) - 2025
 * 
 * Features:
 * - Secure environment variable handling
 * - Production-ready error handling  
 * - Optimized for free hosting limitations
 * - CORS support for frontend deployment
 */

// Load environment configuration
require_once 'config.php';

// Production-ready error handling
if (getEnv('APP_ENV', 'production') === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
}

// Configuration constants
define('API_KEY', getEnv('GEMINI_API_KEY'));
define('MODEL_NAME', getEnv('GEMINI_MODEL_NAME', 'gemini-2.0-flash-lite'));
define('API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models/');
define('MAX_MESSAGE_LENGTH', 4000); // Prevent abuse on free hosting

/**
 * Send secure JSON response with production headers
 */
function sendResponse($data, $httpCode = 200)
{
    // Security headers for production
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // CORS headers for cross-origin requests
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');

    // Response headers
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Handle preflight CORS requests
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(['status' => 'ok'], 200);
}

/**
 * Serve HTML interface for GET requests
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Basic security headers for HTML
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Type: text/html; charset=UTF-8');

    // Serve the main interface
    if (file_exists('index.html')) {
        readfile('index.html');
    } else {
        echo '<!DOCTYPE html><html><head><title>Gemini Chatbot</title></head><body><h1>Gemini AI Chatbot</h1><p>API is running. Please use a proper frontend interface.</p></body></html>';
    }
    exit;
}

// Validate API configuration
if (empty(API_KEY)) {
    sendResponse(['error' => 'Service temporarily unavailable'], 503);
}

// Only accept POST requests for API calls
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

// Get and validate input
$input = file_get_contents('php://input');
if (empty($input)) {
    sendResponse(['error' => 'Request body is required'], 400);
}

$requestData = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(['error' => 'Invalid JSON format'], 400);
}

// Extract and validate message
$message = trim($requestData['message'] ?? '');
$chatHistory = $requestData['chatHistory'] ?? [];

// Input validation
if (empty($message)) {
    sendResponse(['error' => 'Message is required'], 400);
}

if (strlen($message) > MAX_MESSAGE_LENGTH) {
    sendResponse(['error' => 'Message too long. Maximum ' . MAX_MESSAGE_LENGTH . ' characters allowed'], 400);
}

// Sanitize input
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

/**
 * Build contents for Gemini API
 */
function buildContents($chatHistory, $currentMessage)
{
    $contents = [];

    // Limit chat history to prevent API limits on free hosting
    $chatHistory = array_slice($chatHistory, -10);

    foreach ($chatHistory as $chat) {
        if (!isset($chat['sender']) || !isset($chat['message'])) continue;

        $role = ($chat['sender'] === 'user') ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => htmlspecialchars($chat['message'], ENT_QUOTES, 'UTF-8')]]
        ];
    }

    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $currentMessage]]
    ];

    return $contents;
}

// Prepare API request
$requestBody = [
    'contents' => buildContents($chatHistory, $message),
    'generationConfig' => [
        'maxOutputTokens' => 1000,  // Limit for free hosting
        'temperature' => 0.7
    ]
];

$url = API_BASE_URL . MODEL_NAME . ':generateContent?key=' . API_KEY;

// Make cURL request with optimized settings for free hosting
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestBody),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: ChatBot/1.0'
    ],
    CURLOPT_TIMEOUT => 30,        // Reduced timeout for free hosting
    CURLOPT_CONNECTTIMEOUT => 10,
    // SSL verification: enabled in production, disabled in development
    CURLOPT_SSL_VERIFYPEER => (getEnv('APP_ENV', 'production') === 'production'),
    CURLOPT_SSL_VERIFYHOST => (getEnv('APP_ENV', 'production') === 'production') ? 2 : 0,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_MAXREDIRS => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle errors
if ($curlError) {
    error_log("API Error: " . $curlError);
    sendResponse(['error' => 'Service temporarily unavailable'], 503);
}

if ($httpCode !== 200) {
    error_log("API HTTP Error: $httpCode - $response");
    sendResponse(['error' => 'AI service unavailable'], 503);
}

$result = json_decode($response, true);

// Extract and return response
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $result['candidates'][0]['content']['parts'][0]['text'];

    // Basic output sanitization
    $reply = strip_tags($reply);
    $reply = trim($reply);

    sendResponse([
        'reply' => $reply,
        'timestamp' => date('c'),
        'model' => MODEL_NAME
    ]);
} else {
    error_log("Unexpected API response: " . json_encode($result));
    sendResponse(['error' => 'Invalid response from AI service'], 500);
}
