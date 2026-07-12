<?php

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$user   = cekAuth(); // GET boleh viewer & admin

// ============================================================
// GET
// ============================================================
if ($method === 'GET') {
    $db = getDB();

    // Riwayat yang sudah dikembalikan
    if (isset($_GET['riwayat'])) {
        $stmt = $db->query('
            SELECT pb.*, s.nis, s.nama, s.kelas, u.nama AS dicatat_oleh_nama
            FROM peminjaman_buku pb
            JOIN siswa s ON pb.siswa_id = s.id
            JOIN users u ON pb.dicatat_oleh = u.id
            WHERE pb.status = "dikembalikan"
            ORDER BY pb.tanggal_kembali DESC LIMIT 100
        ');
        $rows = $stmt->fetch_all(MYSQLI_ASSOC);
        $db->close();
        jsonResponse(['data' => $rows]);
    }

    // Buku yang sedang dipinjam siswa tertentu
    if (isset($_GET['siswa_id'])) {
        $sid = intval($_GET['siswa_id']);
        $stmt = $db->prepare('
            SELECT pb.* FROM peminjaman_buku pb
            WHERE pb.siswa_id = ? AND pb.status = "dipinjam"
            ORDER BY pb.tanggal_pinjam DESC
        ');
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $db->close();
        jsonResponse(['data' => $rows]);
    }

    // Default: tap yang menunggu diisi judul + daftar sedang dipinjam
    $pending = $db->query('
        SELECT tp.id AS tap_id, tp.waktu_tap, tp.device_id,
               s.id AS siswa_id, s.nis, s.nama, s.kelas
        FROM tap_perpus_pending tp
        JOIN siswa s ON tp.siswa_id = s.id
        WHERE tp.selesai = 0
        ORDER BY tp.waktu_tap DESC
    ')->fetch_all(MYSQLI_ASSOC);

    $dipinjam = $db->query('
        SELECT pb.*, s.nis, s.nama, s.kelas
        FROM peminjaman_buku pb
        JOIN siswa s ON pb.siswa_id = s.id
        WHERE pb.status = "dipinjam"
        ORDER BY pb.tanggal_pinjam DESC
    ')->fetch_all(MYSQLI_ASSOC);

    $db->close();
    jsonResponse(['pending' => $pending, 'dipinjam' => $dipinjam]);
}

// ============================================================
// POST — catat peminjaman ATAU tandai kembali
// ============================================================
if ($method === 'POST') {
    cekAdmin(); // hanya admin/petugas yang boleh input judul & tandai kembali
    $body = getBody();
    $db   = getDB();

    // ── TANDAI DIKEMBALIKAN ──
    if (isset($_GET['kembali'])) {
        $id = intval($body['id'] ?? 0);
        if (!$id) { $db->close(); jsonResponse(['error' => 'ID peminjaman wajib'], 400); }

        $tglKembali = date('Y-m-d');
        $stmt = $db->prepare(
            "UPDATE peminjaman_buku SET status='dikembalikan', tanggal_kembali=? WHERE id=? AND status='dipinjam'"
        );
        $stmt->bind_param('si', $tglKembali, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        $db->close();

        if (!$ok) jsonResponse(['error' => 'Data tidak ditemukan atau sudah dikembalikan'], 400);
        jsonResponse(['success' => true, 'pesan' => 'Buku ditandai sudah dikembalikan']);
    }

    // ── CATAT PEMINJAMAN BARU (dari tap pending) ──
    $tapId     = intval($body['tap_id'] ?? 0);
    $judulBuku = trim($body['judul_buku'] ?? '');

    if (!$tapId)     { $db->close(); jsonResponse(['error' => 'tap_id wajib'], 400); }
    if (!$judulBuku) { $db->close(); jsonResponse(['error' => 'Judul buku wajib diisi'], 400); }

    // Ambil data tap pending
    $stmt = $db->prepare('SELECT * FROM tap_perpus_pending WHERE id = ? AND selesai = 0');
    $stmt->bind_param('i', $tapId);
    $stmt->execute();
    $tap = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tap) { $db->close(); jsonResponse(['error' => 'Tap tidak ditemukan atau sudah diproses'], 404); }

    // Simpan peminjaman
    $tglPinjam = date('Y-m-d');
    $stmt = $db->prepare(
        'INSERT INTO peminjaman_buku (siswa_id, judul_buku, device_id, tanggal_pinjam, status, dicatat_oleh)
         VALUES (?, ?, ?, ?, "dipinjam", ?)'
    );
    $stmt->bind_param('isssi', $tap['siswa_id'], $judulBuku, $tap['device_id'], $tglPinjam, $user['id']);
    $stmt->execute();
    $peminjamanId = $db->insert_id;
    $stmt->close();

    // Tandai tap sebagai selesai diproses
    $stmt = $db->prepare('UPDATE tap_perpus_pending SET selesai = 1 WHERE id = ?');
    $stmt->bind_param('i', $tapId);
    $stmt->execute();
    $stmt->close();
    $db->close();

    jsonResponse([
        'success' => true,
        'id'      => $peminjamanId,
        'pesan'   => "Peminjaman '$judulBuku' tercatat"
    ]);
}

jsonResponse(['error' => 'Method tidak didukung'], 405);