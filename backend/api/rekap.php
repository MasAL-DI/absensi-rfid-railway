<?php
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'Method tidak didukung'], 405);
cekAuth();

$kelas  = $_GET['kelas']  ?? '';
$bulan  = $_GET['bulan']  ?? date('Y-m');
$format = $_GET['format'] ?? 'json'; // json atau csv

if (!$kelas) jsonResponse(['error' => 'Parameter kelas wajib'], 400);

$db   = getDB();
$stmt = $db->prepare('
    SELECT
        s.nis, s.nama, s.kelas,
        SUM(CASE WHEN a.status_masuk="hadir"     THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_masuk="terlambat" THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL       THEN 1 ELSE 0 END) AS alpha,
        COUNT(a.id)                                                   AS total_hadir,
        SUM(CASE WHEN a.waktu_pulang IS NOT NULL  THEN 1 ELSE 0 END) AS total_pulang
    FROM siswa s
    LEFT JOIN absensi a
        ON s.id = a.siswa_id AND DATE_FORMAT(a.tanggal,"%Y-%m") = ?
    WHERE s.kelas = ? AND s.aktif = 1
    GROUP BY s.id ORDER BY s.nama
');
$stmt->bind_param('ss', $bulan, $kelas);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

// ===== FORMAT CSV =====
if ($format === 'csv') {
    $namaFile = "rekap_{$kelas}_{$bulan}.csv";
    $namaFile = str_replace([' ', '/'], '_', $namaFile);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM UTF-8 supaya Excel bisa baca karakter Indonesia
    fputs($out, "\xEF\xBB\xBF");

    // Header kolom
    fputcsv($out, ['No', 'NIS', 'Nama', 'Kelas', 'Hadir', 'Terlambat', 'Alpha', 'Total Hadir', 'Total Pulang']);

    // Data
    $no = 1;
    foreach ($rows as $r) {
        fputcsv($out, [
            $no++,
            $r['nis'],
            $r['nama'],
            $r['kelas'],
            $r['hadir']       ?? 0,
            $r['terlambat']   ?? 0,
            $r['alpha']       ?? 0,
            $r['total_hadir'] ?? 0,
            $r['total_pulang']?? 0,
        ]);
    }

    // Baris total di bawah
    fputcsv($out, []);
    fputcsv($out, [
        '', '', 'TOTAL', '',
        array_sum(array_column($rows, 'hadir')),
        array_sum(array_column($rows, 'terlambat')),
        array_sum(array_column($rows, 'alpha')),
        '', ''
    ]);

    fclose($out);
    exit;
}

// ===== FORMAT JSON (default) =====
jsonResponse(['kelas' => $kelas, 'bulan' => $bulan, 'data' => $rows]);