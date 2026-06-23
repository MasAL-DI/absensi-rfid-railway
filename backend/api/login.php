<?php

/**
 * POST /api/login.php
 * Login endpoint dengan validation & response yang konsisten
 */

require_once '../config.php';

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

// Get request body
$body = getBody();

// Validate input
$rules = [
    'username' => ['required' => true, 'min' => 1],
    'password' => ['required' => true, 'min' => 1]
];

if (!Validator::validate($body, $rules)) {
    Response::validationError(Validator::errors());
}

$username = trim($body['username']);
$password = trim($body['password']);

// Query user
$db = Database::getInstance();
$user = $db->fetchOne(
    'SELECT id, username, nama, role, password FROM users WHERE username = ? AND aktif = 1',
    [$username],
    's'
);

// Check user exists & password valid
if (!$user || !password_verify($password, $user['password'])) {
    Response::error('Username atau password salah', 401);
}

// Create token
$token = Token::forUser($user['id'], $user['username'], $user['role'], 8);

// Response success
Response::success([
    'token' => $token,
    'nama' => $user['nama'],
    'role' => $user['role']
], 'Login berhasil');

