<?php
require_once '../config.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    cekAuth();
    $kelas = $_GET['kelas'] ?? '';
    $db    = getDB();
    $query = 'SELECT id,nis,nama,kelas,jk,uid_rfid,hp_ortu FROM siswa WHERE aktif=1';
    $params = []; $types = '';
    if ($kelas) { $query .= ' AND kelas=?'; $params[] = $kelas; $types .= 's'; }
    $query .= ' ORDER BY kelas, nama';
    if ($params) {
        $stmt = $db->prepare($query); $stmt->bind_param($types, ...$params);
        $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    } else {
        $rows = $db->query($query)->fetch_all(MYSQLI_ASSOC);
    }
    $db->close();
    jsonResponse($rows);
}

if ($method === 'POST') {
    cekAuth();
    $body = getBody();
    $nis  = trim($body['nis'] ?? ''); $nama = trim($body['nama'] ?? '');
    $kelas= trim($body['kelas'] ?? ''); $jk = trim($body['jk'] ?? 'L');
    $uid  = trim($body['uid_rfid'] ?? ''); $hp = trim($body['hp_ortu'] ?? '');
    if (!$nis || !$nama || !$kelas) jsonResponse(['error' => 'NIS, nama, kelas wajib'], 400);

    $db = getDB();

    // ===== CEK UID SUDAH DIPAKAI SISWA LAIN =====
    if ($uid) {
        $stmt = $db->prepare('SELECT nis, nama FROM siswa WHERE uid_rfid = ? AND nis != ? AND aktif = 1');
        $stmt->bind_param('ss', $uid, $nis);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dup) {
            $db->close();
            jsonResponse([
                'error' => "UID '$uid' sudah dipakai siswa lain: {$dup['nama']} (NIS: {$dup['nis']})"
            ], 400);
        }
    }
    // ===== END CEK UID =====

    $stmt = $db->prepare("INSERT INTO siswa (nis,nama,kelas,jk,uid_rfid,hp_ortu) VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE nama=VALUES(nama),kelas=VALUES(kelas),jk=VALUES(jk),uid_rfid=IF(VALUES(uid_rfid)!='',VALUES(uid_rfid),uid_rfid),hp_ortu=VALUES(hp_ortu)");
    $stmt->bind_param('ssssss', $nis, $nama, $kelas, $jk, $uid, $hp);
    $stmt->execute(); $stmt->close(); $db->close();
    jsonResponse(['success' => true, 'pesan' => 'Siswa berhasil disimpan']);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);