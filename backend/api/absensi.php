<?php

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// POST — dari ESP32
if ($method === 'POST') {
    $body      = getBody();
    $uid       = trim($body['uid']       ?? '');
    $kelasEsp  = trim($body['kelas']     ?? '');   // kelas dari ESP32
    $deviceId  = trim($body['device_id'] ?? '');   // ID perangkat (opsional, untuk log)

    if (!$uid) jsonResponse(['status' => 'error', 'pesan' => 'UID wajib'], 400);

    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM siswa WHERE uid_rfid = ? AND aktif = 1');
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$siswa) {
        $db->close();
        jsonResponse(['status' => 'tidak_terdaftar', 'pesan' => 'Kartu tidak terdaftar', 'uid' => $uid]);
    }

    // Kelas: prioritaskan dari database siswa, fallback ke ESP32
    $kelasFinal = $siswa['kelas'] ?: $kelasEsp;

    $tanggal = date('Y-m-d');
    $waktu   = date('H:i:s');

    // ===== RATE LIMITING: cegah tap ganda dalam 10 detik =====
    $stmt = $db->prepare(
        "SELECT updated_at FROM absensi
         WHERE siswa_id = ? AND tanggal = ?
         ORDER BY updated_at DESC LIMIT 1"
    );
    $stmt->bind_param('is', $siswa['id'], $tanggal);
    $stmt->execute();
    $tapTerakhir = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($tapTerakhir) {
        $selisihDetik = time() - strtotime($tapTerakhir['updated_at']);
        if ($selisihDetik < 10) {
            $db->close();
            jsonResponse([
                'status' => 'terlalu_cepat',
                'nama'   => $siswa['nama'],
                'pesan'  => 'Tap terlalu cepat, tunggu sebentar',
                'tunggu' => 10 - $selisihDetik
            ]);
        }
    }
    // ===== END RATE LIMITING =====

    // CEK: apakah ada izin keluar aktif untuk siswa ini?

    $stmt = $db->prepare(
        "SELECT * FROM izin_keluar
         WHERE siswa_id = ? AND tanggal = ? AND status IN ('menunggu','keluar')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('is', $siswa['id'], $tanggal);
    $stmt->execute();
    $izin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Kalau ada izin menunggu → ini tap keluar sementara
    if ($izin && $izin['status'] === 'menunggu') {
        $stmt = $db->prepare(
            "UPDATE izin_keluar SET jam_keluar = ?, status = 'keluar', updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('si', $waktu, $izin['id']);
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse([
            'status' => 'izin_keluar',
            'nama'   => $siswa['nama'],
            'kelas'  => $siswa['kelas'],
            'waktu'  => $waktu,
            'pesan'  => 'Silakan keluar, jangan lupa tap saat kembali'
        ]);
    }

    // Kalau sedang keluar → ini tap kembali
    if ($izin && $izin['status'] === 'keluar') {
        $stmt = $db->prepare(
            "UPDATE izin_keluar SET jam_kembali = ?, status = 'kembali', updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('si', $waktu, $izin['id']);
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse([
            'status' => 'kembali',
            'nama'   => $siswa['nama'],
            'kelas'  => $siswa['kelas'],
            'waktu'  => $waktu,
            'pesan'  => 'Selamat datang kembali!'
        ]);
    }

    // TAP NORMAL: masuk atau pulang
    
    $stmt = $db->prepare('SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?');
    $stmt->bind_param('is', $siswa['id'], $tanggal);
    $stmt->execute();
    $absensi = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Ambil jadwal hari ini
    $hari   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];
    $stmt   = $db->prepare('SELECT * FROM jadwal WHERE hari = ?');
    $stmt->bind_param('s', $hari);
    $stmt->execute();
    $jadwal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $batasTelat  = $jadwal['batas_telat']  ?? '07:30:00';
    $jamPulang   = $jadwal['jam_pulang']   ?? '15:00:00';

    // Belum ada record hari ini → TAP MASUK
    if (!$absensi) {
        $statusMasuk = $waktu > $batasTelat ? 'terlambat' : 'hadir';

        $stmt = $db->prepare(
            'INSERT INTO absensi (siswa_id, kelas, tanggal, waktu_masuk, status_masuk, uid_rfid, device_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssss',
            $siswa['id'], $kelasFinal, $tanggal, $waktu, $statusMasuk, $uid, $deviceId
        );
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse([
            'status'    => $statusMasuk,
            'tipe'      => 'masuk',
            'nama'      => $siswa['nama'],
            'kelas'     => $kelasFinal,
            'nis'       => $siswa['nis'],
            'waktu'     => $waktu,
            'device_id' => $deviceId,
            'pesan'     => $statusMasuk === 'hadir' ? 'Selamat datang!' : 'Harap lebih tepat waktu!'
        ]);
    }

    // Sudah tap masuk, belum tap pulang → TAP PULANG
    if ($absensi && $absensi['waktu_pulang'] === null) {
        $statusPulang = $waktu < $jamPulang ? 'lebih_awal' : 'tepat_waktu';

        $stmt = $db->prepare(
            "UPDATE absensi SET waktu_pulang = ?, status_pulang = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssi', $waktu, $statusPulang, $absensi['id']);
        $stmt->execute();
        $stmt->close();
        $db->close();

        jsonResponse([
            'status' => $statusPulang,
            'tipe'   => 'pulang',
            'nama'   => $siswa['nama'],
            'kelas'  => $siswa['kelas'],
            'waktu'  => $waktu,
            'pesan'  => $statusPulang === 'tepat_waktu' ? 'Sampai jumpa!' : 'Pulang lebih awal'
        ]);
    }

    // Sudah tap masuk & pulang → selesai
    $db->close();
    jsonResponse([
        'status' => 'sudah_lengkap',
        'nama'   => $siswa['nama'],
        'kelas'  => $siswa['kelas'],
        'pesan'  => 'Absensi hari ini sudah lengkap'
    ]);
}

// =============================================
// GET — rekap harian
// =============================================
if ($method === 'GET') {
    cekAuth();

    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $kelas   = $_GET['kelas']   ?? '';

    $db = getDB();

    $query = '
        SELECT s.nis, s.nama, s.kelas,
               a.waktu_masuk, a.status_masuk,
               a.waktu_pulang, a.status_pulang,
               a.tanggal
        FROM siswa s
        LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
        WHERE s.aktif = 1
    ';
    $params = [$tanggal];
    $types  = 's';

    if ($kelas) { $query .= ' AND s.kelas = ?'; $params[] = $kelas; $types .= 's'; }
    $query .= ' ORDER BY s.kelas, s.nama';

    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    jsonResponse(['tanggal' => $tanggal, 'data' => $rows]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);