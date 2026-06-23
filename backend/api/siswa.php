<?php

/**
 * GET  /api/siswa.php          - Get all students (with optional class filter)
 * POST /api/siswa.php          - Create or update student
 */

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();

// ========== GET: Retrieve students ==========
if ($method === 'GET') {
    Token::verifyOrFail(); // Require authentication
    
    $kelas = trim($_GET['kelas'] ?? '');
    
    $query = 'SELECT id, nis, nama, kelas, jk, uid_rfid, hp_ortu FROM siswa WHERE aktif = 1';
    $params = [];
    $types = '';
    
    if ($kelas) {
        $query .= ' AND kelas = ?';
        $params[] = $kelas;
        $types .= 's';
    }
    
    $query .= ' ORDER BY kelas, nama';
    
    $rows = $db->fetchAll($query, $params, $types);
    Response::success($rows, 'Data siswa berhasil diambil');
}

// ========== POST: Create or update student ==========
if ($method === 'POST') {
    Token::verifyOrFail(); // Require authentication
    
    $body = getBody();
    
    // Validation rules
    $rules = [
        'nis' => ['required' => true, 'min' => 1, 'max' => 20],
        'nama' => ['required' => true, 'min' => 3, 'max' => 100],
        'kelas' => ['required' => true, 'min' => 1, 'max' => 10],
        'jk' => ['required' => false, 'in' => ['L', 'P']],
        'uid_rfid' => ['required' => false, 'max' => 30],
        'hp_ortu' => ['required' => false, 'max' => 20]
    ];
    
    Validator::validateOrFail($body, $rules);
    
    // Extract & sanitize data
    $nis = trim($body['nis']);
    $nama = trim($body['nama']);
    $kelas = trim($body['kelas']);
    $jk = trim($body['jk'] ?? 'L');
    $uid = trim($body['uid_rfid'] ?? '');
    $hp = trim($body['hp_ortu'] ?? '');
    
    // Check if UID already used by another student (if provided)
    if ($uid) {
        $existing = $db->fetchOne(
            'SELECT nis, nama FROM siswa WHERE uid_rfid = ? AND nis != ? AND aktif = 1',
            [$uid, $nis],
            'ss'
        );
        
        if ($existing) {
            Response::error(
                "UID '$uid' sudah dipakai siswa lain: {$existing['nama']} (NIS: {$existing['nis']})",
                409
            );
        }
    }
    
    // Insert or update
    $result = $db->execute(
        "INSERT INTO siswa (nis, nama, kelas, jk, uid_rfid, hp_ortu) 
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
            nama = VALUES(nama),
            kelas = VALUES(kelas),
            jk = VALUES(jk),
            uid_rfid = IF(VALUES(uid_rfid) != '', VALUES(uid_rfid), uid_rfid),
            hp_ortu = VALUES(hp_ortu)",
        [$nis, $nama, $kelas, $jk, $uid, $hp],
        'ssssss'
    );
    
    Response::success(
        ['insertId' => $result['insertId'], 'affected' => $result['affected']],
        'Siswa berhasil disimpan'
    );
}

// Method not allowed
Response::methodNotAllowed();
