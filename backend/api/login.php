<?php

// api/login.php

require_once '../../backend/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method tidak didukung'], 405);

$body     = getBody();
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

if (!$username || !$password) jsonResponse(['error' => 'Username dan password wajib'], 400);

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if (!$user || !password_verify($password, $user['password']))
    jsonResponse(['error' => 'Username atau password salah'], 401);

$token = buatToken([
    'id'       => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'exp'      => time() + (8 * 3600)
]);

jsonResponse(['token' => $token, 'nama' => $user['nama'], 'role' => $user['role']]);
