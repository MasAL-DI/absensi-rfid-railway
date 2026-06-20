<?php
// api/sesi.php
// GET  /api/sesi.php                  → daftar sesi (aktif + riwayat hari ini)
// GET  /api/sesi.php?id=5             → detail 1 sesi + daftar siswa yang tap
// POST /api/sesi.php                  → buka sesi baru
// POST /api/sesi.php?tutup=1          → tutup sesi { id }

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user   = cekAuth();

// ============================================================
// GET — daftar sesi / detail sesi / daftar device
// ============================================================
if ($method === 'GET') {
    $db = getDB();

    // Daftar semua device_id yang pernah tercatat (dari absensi & tap_sesi)
    if (isset($_GET['devices'])) {
        $rows1 = $db->query('SELECT DISTINCT device_id FROM absensi WHERE device_id IS NOT NULL AND device_id != ""')->fetch_all(MYSQLI_ASSOC);
        $rows2 = $db->query('SELECT DISTINCT device_id FROM tap_sesi WHERE device_id IS NOT NULL AND device_id != ""')->fetch_all(MYSQLI_ASSOC);
        $all = array_unique(array_merge(array_column($rows1,'device_id'), array_column($rows2,'device_id')));
        sort($all);
        $db->close();
        jsonResponse(['devices' => array_values($all)]);
    }

    // Detail 1 sesi + siapa saja yang sudah tap
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);

        $stmt = $db->prepare('
            SELECT sa.*, u.nama AS dibuka_oleh_nama
            FROM sesi_absensi sa
            JOIN users u ON sa.dibuka_oleh = u.id
            WHERE sa.id = ?
        ');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sesi = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sesi) { $db->close(); jsonResponse(['error' => 'Sesi tidak ditemukan'], 404); }

        // Device yang dipakai sesi ini
        $stmt = $db->prepare('SELECT device_id FROM sesi_device WHERE sesi_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $devices = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'device_id');
        $stmt->close();

        // Siswa yang sudah tap di sesi ini
        $stmt = $db->prepare('
            SELECT s.nis, s.nama, s.kelas, ts.device_id, ts.waktu_tap
            FROM tap_sesi ts JOIN siswa s ON ts.siswa_id = s.id
            WHERE ts.sesi_id = ? ORDER BY ts.waktu_tap
        ');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $tapList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $db->close();

        $sesi['devices']   = $devices;
        $sesi['tap_list']  = $tapList;
        $sesi['total_tap'] = count($tapList);

        jsonResponse($sesi);
    }

    // Daftar sesi hari ini (aktif di atas, lalu yang sudah selesai)
    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $stmt = $db->prepare('
        SELECT sa.*, u.nama AS dibuka_oleh_nama,
               (SELECT COUNT(*) FROM tap_sesi WHERE sesi_id = sa.id) AS total_tap
        FROM sesi_absensi sa
        JOIN users u ON sa.dibuka_oleh = u.id
        WHERE sa.tanggal = ?
        ORDER BY (sa.status = "aktif") DESC, sa.jam_buka DESC
    ');
    $stmt->bind_param('s', $tanggal);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Lampirkan device tiap sesi
    foreach ($list as &$s) {
        $stmt = $db->prepare('SELECT device_id FROM sesi_device WHERE sesi_id = ?');
        $stmt->bind_param('i', $s['id']);
        $stmt->execute();
        $s['devices'] = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'device_id');
        $stmt->close();
    }

    $db->close();
    jsonResponse(['tanggal' => $tanggal, 'data' => $list]);
}

// ============================================================
// POST — buka sesi baru ATAU tutup sesi
// ============================================================
if ($method === 'POST') {
    $body = getBody();
    $db   = getDB();

    // ── TUTUP SESI ──
    if (isset($_GET['tutup'])) {
        $id = intval($body['id'] ?? 0);
        if (!$id) { $db->close(); jsonResponse(['error' => 'ID sesi wajib'], 400); }

        $stmt = $db->prepare(
            "UPDATE sesi_absensi SET status='selesai', jam_tutup=? WHERE id=? AND status='aktif'"
        );
        $jamTutup = date('H:i:s');
        $stmt->bind_param('si', $jamTutup, $id);
        $stmt->execute();
        $terpengaruh = $stmt->affected_rows;
        $stmt->close();

        if ($terpengaruh === 0) {
            $db->close();
            jsonResponse(['error' => 'Sesi tidak ditemukan atau sudah ditutup'], 400);
        }

        // Lepas device dari sesi supaya kembali netral (bisa absen masuk/pulang lagi)
        $stmt = $db->prepare('DELETE FROM sesi_device WHERE sesi_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse(['success' => true, 'pesan' => 'Sesi ditutup, device kembali netral']);
    }

    // ── BUKA SESI BARU ──
    $namaSesi  = trim($body['nama_sesi'] ?? '');
    $devices   = $body['devices'] ?? []; // array of device_id, contoh: ["MASJID-1","MASJID-2"]

    if (!$namaSesi)               { $db->close(); jsonResponse(['error' => 'Nama sesi wajib'], 400); }
    if (!is_array($devices) || !count($devices)) {
        $db->close(); jsonResponse(['error' => 'Pilih minimal 1 device'], 400);
    }

    // Cek apakah ada device yang sudah dipakai sesi aktif lain
    $devicesBentrok = [];
    foreach ($devices as $dev) {
        $dev = trim($dev);
        $stmt = $db->prepare('
            SELECT sa.nama_sesi FROM sesi_device sd
            JOIN sesi_absensi sa ON sd.sesi_id = sa.id
            WHERE sd.device_id = ? AND sa.status = "aktif"
        ');
        $stmt->bind_param('s', $dev);
        $stmt->execute();
        $bentrok = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($bentrok) $devicesBentrok[] = "$dev (sedang dipakai sesi '{$bentrok['nama_sesi']}')";
    }

    if ($devicesBentrok) {
        $db->close();
        jsonResponse(['error' => 'Device berikut sedang dipakai sesi lain: ' . implode(', ', $devicesBentrok)], 400);
    }

    // Buat sesi
    $tanggal  = date('Y-m-d');
    $jamBuka  = date('H:i:s');

    $stmt = $db->prepare(
        'INSERT INTO sesi_absensi (nama_sesi, tanggal, dibuka_oleh, jam_buka, status)
         VALUES (?, ?, ?, ?, "aktif")'
    );
    $stmt->bind_param('ssis', $namaSesi, $tanggal, $user['id'], $jamBuka);
    $stmt->execute();
    $sesiId = $db->insert_id;
    $stmt->close();

    // Daftarkan device ke sesi ini
    $stmt = $db->prepare('INSERT INTO sesi_device (sesi_id, device_id) VALUES (?, ?)');
    foreach ($devices as $dev) {
        $dev = trim($dev);
        $stmt->bind_param('is', $sesiId, $dev);
        $stmt->execute();
    }
    $stmt->close();
    $db->close();

    jsonResponse([
        'success'   => true,
        'id'        => $sesiId,
        'nama_sesi' => $namaSesi,
        'devices'   => $devices,
        'pesan'     => "Sesi '$namaSesi' dibuka. Device siap dipakai: " . implode(', ', $devices)
    ]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);