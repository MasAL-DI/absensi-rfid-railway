<?php

// api/dashboard.php

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'Method tidak didukung'], 405);
cekAuth();

$db      = getDB();
$tanggal = date('Y-m-d');

// Statistik kehadiran
$stmt = $db->prepare('
    SELECT
        COUNT(DISTINCT s.id)                                          AS total_siswa,
        SUM(CASE WHEN a.status_masuk="hadir"     THEN 1 ELSE 0 END)  AS hadir,
        SUM(CASE WHEN a.status_masuk="terlambat" THEN 1 ELSE 0 END)  AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL       THEN 1 ELSE 0 END)  AS alpha,
        SUM(CASE WHEN a.waktu_pulang IS NOT NULL  THEN 1 ELSE 0 END)  AS sudah_pulang,
        SUM(CASE WHEN a.waktu_masuk IS NOT NULL
                  AND a.waktu_pulang IS NULL       THEN 1 ELSE 0 END)  AS masih_di_sekolah
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1
');
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Rekap per kelas
$stmt = $db->prepare('
    SELECT
        s.kelas,
        COUNT(s.id)                                                    AS total_siswa,
        SUM(CASE WHEN a.status_masuk="hadir"     THEN 1 ELSE 0 END)   AS hadir,
        SUM(CASE WHEN a.status_masuk="terlambat" THEN 1 ELSE 0 END)   AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL       THEN 1 ELSE 0 END)   AS alpha
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1
    GROUP BY s.kelas ORDER BY s.kelas
');
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$perKelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 10 tap terakhir
$stmt = $db->prepare('
    SELECT s.nama, s.kelas, a.waktu_masuk, a.waktu_pulang, a.status_masuk, a.updated_at
    FROM absensi a JOIN siswa s ON a.siswa_id = s.id
    WHERE a.tanggal = ?
    ORDER BY a.updated_at DESC LIMIT 10
');
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$scanTerakhir = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Izin keluar aktif (menunggu tap atau sedang keluar)
$stmt = $db->prepare("
    SELECT ik.id, s.nama, s.kelas, ik.alasan, ik.jam_keluar, ik.status, ik.created_at
    FROM izin_keluar ik JOIN siswa s ON ik.siswa_id = s.id
    WHERE ik.tanggal = ? AND ik.status IN ('menunggu','keluar')
    ORDER BY ik.created_at DESC
");
$stmt->bind_param('s', $tanggal);
$stmt->execute();
$izinAktif = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db->close();

jsonResponse([
    'tanggal'      => $tanggal,
    'stats'        => $stats,
    'perKelas'     => $perKelas,
    'scanTerakhir' => $scanTerakhir,
    'izinAktif'    => $izinAktif
]);
