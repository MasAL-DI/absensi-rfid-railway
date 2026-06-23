<?php

/**
 * ============================================================
 * BOOTSTRAP & CONFIGURATION
 * ============================================================
 * Load helpers dan konfigurasi aplikasi
 */

// ===== SETUP AWAL =====
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ===== LOAD HELPERS =====
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/Database.php';
require_once __DIR__ . '/helpers/Token.php';

// ===== CORS HANDLER =====
function setupCors() {
    // Default trusted origins
    $allowedOrigins = [
        'https://absensi-rfid-railway.vercel.app',
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
    ];

    // Allow overriding via environment variable (comma-separated)
    $envOrigins = getenv('CORS_ORIGINS') ?: '';
    if ($envOrigins) {
        $parts = array_map('trim', explode(',', $envOrigins));
        foreach ($parts as $p) {
            if ($p !== '') $allowedOrigins[] = $p;
        }
        // make unique
        $allowedOrigins = array_values(array_unique($allowedOrigins));
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
    } else {
        // non-browser clients (ESP32, curl) may not send Origin — allow all for those
        // but in production you may want to restrict this further.
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ===== HELPER FUNCTIONS (Backward compatibility) =====

/**
 * Get request body
 */
function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

/**
 * Get database connection (backward compatible)
 * Recommendation: Gunakan Database::getInstance() langsung di endpoint baru
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Legacy: jsonResponse (gunakan Response class instead)
 */
function jsonResponse($data, $code = 200) {
    Response::json($data, $code);
}

/**
 * Legacy: buatToken (gunakan Token::create() instead)
 */
function buatToken($payload) {
    return Token::create($payload);
}

/**
 * Legacy: verifikasiToken (gunakan Token::verify() instead)
 */
function verifikasiToken($token) {
    return Token::verify($token);
}

/**
 * Legacy: cekAuth (gunakan Token::verifyOrFail() instead)
 */
function cekAuth() {
    return Token::verifyOrFail();
}

// ===== RUN CORS SETUP =====
setupCors();