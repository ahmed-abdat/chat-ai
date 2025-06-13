<?php

/**
 * Production Environment Configuration Loader
 * Optimized for free hosting platforms (Railway, Render, Fly.io) - 2025
 * 
 * Features:
 * - Secure environment variable loading
 * - Production-ready error handling
 * - Memory efficient
 * - Fallback for missing .env files
 */

/**
 * Load environment variables from .env file or system environment
 * Handles both local development and production deployment
 */
function loadEnvironment($filePath = '.env')
{
    // Try to load from .env file (development/local)
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return; // Fail silently in production
        }

        // Normalize line endings and split
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\''); // Remove quotes

                // Only set if not already set by system environment
                if (!isset($_ENV[$key]) && getenv($key) === false) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    // For production hosting platforms, environment variables are usually
    // already available through the system, so no error if .env is missing
}

/**
 * Get environment variable with fallback support
 * Production-ready with multiple fallback methods
 */
if (!function_exists('getEnv')) {
    function getEnv($key, $default = null)
    {
        // Check multiple sources in order of preference
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;

        // Handle common boolean strings
        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case '1':
                case 'yes':
                case 'on':
                    return true;
                case 'false':
                case '0':
                case 'no':
                case 'off':
                case '':
                    return ($value === '') ? $default : false;
            }
        }

        return $value;
    }
}

/**
 * Validate required environment variables
 * Production-friendly validation with clear error messages
 */
function validateRequiredEnvVars($requiredVars = [])
{
    $missing = [];

    foreach ($requiredVars as $var) {
        $value = getEnv($var);
        if (empty($value) && $value !== 0 && $value !== '0') {
            $missing[] = $var;
        }
    }

    if (!empty($missing)) {
        $error = "Missing required environment variables: " . implode(', ', $missing);

        // Log the error for debugging
        error_log("Configuration Error: $error");

        // In production, show generic error to users
        if (getEnv('APP_ENV', 'production') === 'production') {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Service configuration error']);
            exit;
        } else {
            throw new Exception($error);
        }
    }
}

/**
 * Get database configuration for hosting platforms
 * Many free hosting platforms provide database URLs
 */
function getDatabaseConfig()
{
    $databaseUrl = getEnv('DATABASE_URL');
    if ($databaseUrl) {
        // Parse database URL format: scheme://user:pass@host:port/dbname
        $parsed = parse_url($databaseUrl);
        return [
            'scheme' => $parsed['scheme'] ?? 'mysql',
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 3306,
            'database' => ltrim($parsed['path'] ?? '', '/'),
            'username' => $parsed['user'] ?? '',
            'password' => $parsed['pass'] ?? ''
        ];
    }

    // Fallback to individual environment variables
    return [
        'host' => getEnv('DB_HOST', 'localhost'),
        'port' => getEnv('DB_PORT', 3306),
        'database' => getEnv('DB_NAME', ''),
        'username' => getEnv('DB_USER', ''),
        'password' => getEnv('DB_PASS', '')
    ];
}

// Initialize environment configuration
try {
    // Load environment variables
    loadEnvironment();

    // Validate critical environment variables
    validateRequiredEnvVars(['GEMINI_API_KEY']);

    // Set default values for common hosting platforms AFTER loading
    if (!getEnv('APP_ENV')) {
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
    }

    // Set default model if not specified
    if (!getEnv('GEMINI_MODEL_NAME')) {
        $_ENV['GEMINI_MODEL_NAME'] = 'gemini-2.0-flash-lite';
        putenv('GEMINI_MODEL_NAME=gemini-2.0-flash-lite');
    }
} catch (Exception $e) {
    // Production error handling
    error_log("Environment configuration error: " . $e->getMessage());

    if (getEnv('APP_ENV', 'production') === 'production') {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body><h1>Service Temporarily Unavailable</h1><p>Please try again later.</p></body></html>';
    } else {
        echo "Configuration Error: " . $e->getMessage();
    }
    exit;
}
