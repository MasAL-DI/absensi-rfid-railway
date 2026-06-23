<?php

/**
 * GET /api/dashboard.php
 * Get dashboard statistics for today
 * Returns: stats, perKelas, scanTerakhir, izinAktif
 */

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed();
}

Token::verifyOrFail();

$db = Database::getInstance();
$tanggal = date('Y-m-d');

// ===== Attendance statistics =====
$stats = $db->fetchOne(
    "SELECT
        COUNT(DISTINCT s.id) AS total_siswa,
        SUM(CASE WHEN a.status_masuk='hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_masuk='terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL THEN 1 ELSE 0 END) AS alpha,
        SUM(CASE WHEN a.waktu_pulang IS NOT NULL THEN 1 ELSE 0 END) AS sudah_pulang,
        SUM(CASE WHEN a.waktu_masuk IS NOT NULL AND a.waktu_pulang IS NULL THEN 1 ELSE 0 END) AS masih_di_sekolah
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1",
    [$tanggal],
    's'
);

// ===== Per-class summary =====
$perKelas = $db->fetchAll(
    "SELECT
        s.kelas,
        COUNT(s.id) AS total_siswa,
        SUM(CASE WHEN a.status_masuk='hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_masuk='terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.waktu_masuk IS NULL THEN 1 ELSE 0 END) AS alpha
    FROM siswa s
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE s.aktif = 1
    GROUP BY s.kelas
    ORDER BY s.kelas",
    [$tanggal],
    's'
);

// ===== Last 10 taps =====
$scanTerakhir = $db->fetchAll(
    "SELECT s.nama, s.kelas, a.waktu_masuk, a.waktu_pulang, a.status_masuk, a.updated_at
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    WHERE a.tanggal = ?
    ORDER BY a.updated_at DESC
    LIMIT 10",
    [$tanggal],
    's'
);

// ===== Active permission to leave =====
$izinAktif = $db->fetchAll(
    "SELECT ik.id, s.nama, s.kelas, ik.alasan, ik.jam_keluar, ik.status, ik.created_at
    FROM izin_keluar ik
    JOIN siswa s ON ik.siswa_id = s.id
    WHERE ik.tanggal = ? AND ik.status IN ('menunggu', 'keluar')
    ORDER BY ik.created_at DESC",
    [$tanggal],
    's'
);

Response::success([
    'tanggal' => $tanggal,
    'stats' => $stats,
    'perKelas' => $perKelas,
    'scanTerakhir' => $scanTerakhir,
    'izinAktif' => $izinAktif
], 'Dashboard data retrieved');

