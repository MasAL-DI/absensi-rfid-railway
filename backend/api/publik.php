<?php

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'Method tidak didukung'], 405);


$db      = getDB();
$tanggal = date('Y-m-d');

$stmt = $db->prepare('
    SELECT
        COUNT(DISTINCT s.id)                                          AS total_siswa,
        SUM(CASE WHEN a.status_masuk="hadir"     THEN 1 ELSE 0 END)  AS hadir,
        SUM(CASE WHEN a.status_masuk="terlambat" THEN 1 ELSE 0 END)  AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL       THEN 1 ELSE 0 END)  AS alpha,
        SUM(CASE WHEN a.waktu_pulang IS NOT NULL  THEN 1 ELSE 0 END)  AS sudah_pulang
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1
');
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Rekap per kelas — HANYA angka, TIDAK ADA nama siswa
$stmt = $db->prepare('
    SELECT
        s.kelas,
        COUNT(s.id)                                                   AS total_siswa,
        SUM(CASE WHEN a.status_masuk="hadir"     THEN 1 ELSE 0 END)  AS hadir,
        SUM(CASE WHEN a.status_masuk="terlambat" THEN 1 ELSE 0 END)  AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL       THEN 1 ELSE 0 END)  AS alpha
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1
    GROUP BY s.kelas ORDER BY s.kelas
');
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$perKelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

jsonResponse([
    'tanggal'  => $tanggal,
    'stats'    => $stats,
    'perKelas' => $perKelas,
]);