<?php

// ===== CORS =====
$allowedOrigins = [
    'https://absensi-rfid-railway.vercel.app',
    'http://localhost',
    'http://127.0.0.1',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback untuk ESP32 / non-browser (tidak ada Origin header)
    header("Access-Control-Allow-Origin: *");
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// ===== END CORS =====

define('DB_HOST',     getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER',     getenv('MYSQLUSER')     ?: 'root');
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME',     getenv('MYSQLDATABASE') ?: 'db_absensi');
define('DB_PORT',     getenv('MYSQLPORT')     ?: 3306);

define('JWT_SECRET',  getenv('JWT_SECRET')    ?: 'rahasia_absensi_smkn1pabelan');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Koneksi database gagal: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function buatToken($payload) {
    $header  = rtrim(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '=');
    $payload = rtrim(base64_encode(json_encode($payload)), '=');
    $sig     = rtrim(base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)), '=');
    return "$header.$payload.$sig";
}

function verifikasiToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $sig] = $parts;
    $validSig = rtrim(base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)), '=');
    if ($sig !== $validSig) return false;
    $data = json_decode(base64_decode($payload), true);
    if (isset($data['exp']) && $data['exp'] < time()) return false;
    return $data;
}

function cekAuth() {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token   = str_replace('Bearer ', '', $auth);
    $user    = verifikasiToken($token);
    if (!$user) jsonResponse(['error' => 'Token tidak valid atau kadaluarsa'], 401);
    return $user;
}