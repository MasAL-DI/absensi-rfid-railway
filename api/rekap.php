<?php
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'Method tidak didukung'], 405);
cekAuth();

$kelas = $_GET['kelas'] ?? '';
$bulan = $_GET['bulan'] ?? date('Y-m');
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

jsonResponse(['kelas' => $kelas, 'bulan' => $bulan, 'data' => $rows]);
