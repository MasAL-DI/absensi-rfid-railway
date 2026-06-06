<?php

// api/izin_keluar.php
// POST /api/izin_keluar.php          <- buka sesi izin (satpam)
// GET  /api/izin_keluar.php          <- daftar izin hari ini
// POST /api/izin_keluar.php?batalkan <- batalkan izin


require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user   = cekAuth();


// GET — daftar izin keluar hari ini

if ($method === 'GET') {
    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $db      = getDB();

    $stmt = $db->prepare('
        SELECT
            ik.id,
            s.nis, s.nama, s.kelas,
            ik.alasan,
            ik.jam_keluar, ik.jam_kembali,
            ik.status,
            ik.created_at,
            u.nama AS dibuka_oleh_nama
        FROM izin_keluar ik
        JOIN siswa s ON ik.siswa_id = s.id
        JOIN users  u ON ik.dibuka_oleh = u.id
        WHERE ik.tanggal = ?
        ORDER BY ik.created_at DESC
    ');
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    jsonResponse(['tanggal' => $tanggal, 'data' => $rows]);
}

// POST — buka sesi izin keluar

if ($method === 'POST') {
    $body = getBody();

    // Batalkan izin
    if (isset($_GET['batalkan'])) {
        $id = intval($body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID izin wajib'], 400);

        $db   = getDB();
        $stmt = $db->prepare(
            "UPDATE izin_keluar SET status = 'expired', updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse(['success' => true, 'pesan' => 'Izin dibatalkan']);
    }

    // Buka izin baru
    $siswa_id = intval($body['siswa_id'] ?? 0);
    $alasan   = trim($body['alasan'] ?? '');

    if (!$siswa_id) jsonResponse(['error' => 'siswa_id wajib'], 400);

    $db      = getDB();
    $tanggal = date('Y-m-d');

    // Cek siswa ada
    $stmt = $db->prepare('SELECT id, nama, kelas FROM siswa WHERE id = ? AND aktif = 1');
    $stmt->bind_param('i', $siswa_id);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$siswa) {
        $db->close();
        jsonResponse(['error' => 'Siswa tidak ditemukan'], 404);
    }

    // Cek sudah ada izin aktif
    $stmt = $db->prepare(
        "SELECT id FROM izin_keluar
         WHERE siswa_id = ? AND tanggal = ? AND status IN ('menunggu','keluar')"
    );
    $stmt->bind_param('is', $siswa_id, $tanggal);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $db->close();
        jsonResponse(['error' => 'Siswa sudah memiliki izin keluar aktif'], 400);
    }

    // Cek siswa sudah tap masuk hari ini
    $stmt = $db->prepare(
        'SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ? AND waktu_masuk IS NOT NULL'
    );
    $stmt->bind_param('is', $siswa_id, $tanggal);
    $stmt->execute();
    $sudahMasuk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sudahMasuk) {
        $db->close();
        jsonResponse(['error' => 'Siswa belum tap masuk hari ini'], 400);
    }

    // Buat izin
    $stmt = $db->prepare(
        'INSERT INTO izin_keluar (siswa_id, tanggal, dibuka_oleh, alasan, status)
         VALUES (?, ?, ?, ?, "menunggu")'
    );
    $stmt->bind_param('isis', $siswa_id, $tanggal, $user['id'], $alasan);
    $stmt->execute();
    $newId = $db->insert_id;
    $stmt->close();
    $db->close();

    jsonResponse([
        'success'  => true,
        'id'       => $newId,
        'nama'     => $siswa['nama'],
        'kelas'    => $siswa['kelas'],
        'pesan'    => 'Sesi izin keluar dibuka. Siswa bisa tap kartu di gerbang.',
        'status'   => 'menunggu'
    ]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);
