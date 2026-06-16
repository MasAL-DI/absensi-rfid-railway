<?php
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['error' => 'Method tidak didukung'], 405);
cekAuth();

$kelas  = $_GET['kelas']  ?? '';
$format = $_GET['format'] ?? 'json';   // json | csv
$mode   = $_GET['mode']   ?? 'bulanan'; // harian | mingguan | bulanan

// ─── Parameter per mode ────────────────────────────────────────────────────
// harian   : ?tanggal=2025-01-15
// mingguan : ?minggu=2025-W03   (ISO week, e.g. 2025-W03)
// bulanan  : ?bulan=2025-01
// ─────────────────────────────────────────────────────────────────────────

if (!$kelas) jsonResponse(['error' => 'Parameter kelas wajib'], 400);

$db = getDB();

// ══════════════════════════════════════════════════════════════════════════
// MODE: HARIAN — tampilkan setiap siswa + jam masuk & jam pulang pada 1 hari
// ══════════════════════════════════════════════════════════════════════════
if ($mode === 'harian') {
    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');

    $stmt = $db->prepare('
        SELECT
            s.nis, s.nama, s.kelas,
            a.tanggal,
            a.waktu_masuk,  a.status_masuk,
            a.waktu_pulang, a.status_pulang
        FROM siswa s
        LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
        WHERE s.kelas = ? AND s.aktif = 1
        ORDER BY s.nama
    ');
    $stmt->bind_param('ss', $tanggal, $kelas);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    if ($format === 'csv') {
        $namaFile = "rekap_harian_{$kelas}_{$tanggal}.csv";
        _outputCsvHarian($rows, $namaFile, $tanggal, $kelas);
    }

    jsonResponse(['mode' => 'harian', 'tanggal' => $tanggal, 'kelas' => $kelas, 'data' => $rows]);
}

// ══════════════════════════════════════════════════════════════════════════
// MODE: MINGGUAN — per siswa, detail tiap hari dalam 1 minggu (+ jam masuk/pulang)
// ══════════════════════════════════════════════════════════════════════════
if ($mode === 'mingguan') {
    // Hitung Senin–Sabtu dari ISO week string (e.g. "2025-W03")
    $mingguStr = $_GET['minggu'] ?? date('Y-\WW');  // e.g. 2025-W03

    // Parse minggu → tanggal Senin
    $dto      = new DateTime();
    $dto->setISODate((int)substr($mingguStr, 0, 4), (int)substr($mingguStr, 6));
    $senin    = $dto->format('Y-m-d');
    $sabtu    = (clone $dto)->modify('+5 days')->format('Y-m-d');

    // Ambil semua absensi dalam rentang Senin–Sabtu
    $stmt = $db->prepare('
        SELECT
            s.nis, s.nama, s.kelas,
            a.tanggal,
            a.waktu_masuk,  a.status_masuk,
            a.waktu_pulang, a.status_pulang
        FROM siswa s
        LEFT JOIN absensi a
            ON s.id = a.siswa_id AND a.tanggal BETWEEN ? AND ?
        WHERE s.kelas = ? AND s.aktif = 1
        ORDER BY s.nama, a.tanggal
    ');
    $stmt->bind_param('sss', $senin, $sabtu, $kelas);
    $stmt->execute();
    $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    // Susun per siswa → array tanggal
    $hariList = [];
    $cur = clone $dto;
    for ($i = 0; $i < 6; $i++) {
        $hariList[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }

    $siswaMap = [];
    foreach ($rawRows as $r) {
        $key = $r['nis'];
        if (!isset($siswaMap[$key])) {
            $siswaMap[$key] = [
                'nis'   => $r['nis'],
                'nama'  => $r['nama'],
                'kelas' => $r['kelas'],
                'hari'  => [],
            ];
            foreach ($hariList as $tgl) {
                $siswaMap[$key]['hari'][$tgl] = ['waktu_masuk'=>null,'status_masuk'=>null,'waktu_pulang'=>null,'status_pulang'=>null];
            }
        }
        if ($r['tanggal']) {
            $siswaMap[$key]['hari'][$r['tanggal']] = [
                'waktu_masuk'   => $r['waktu_masuk'],
                'status_masuk'  => $r['status_masuk'],
                'waktu_pulang'  => $r['waktu_pulang'],
                'status_pulang' => $r['status_pulang'],
            ];
        }
    }

    $rows = array_values($siswaMap);

    if ($format === 'csv') {
        $namaFile = "rekap_mingguan_{$kelas}_{$mingguStr}.csv";
        _outputCsvMingguan($rows, $namaFile, $hariList, $kelas, $mingguStr);
    }

    jsonResponse([
        'mode'    => 'mingguan',
        'minggu'  => $mingguStr,
        'senin'   => $senin,
        'sabtu'   => $sabtu,
        'kelas'   => $kelas,
        'hari'    => $hariList,
        'data'    => $rows,
    ]);
}

// ══════════════════════════════════════════════════════════════════════════
// MODE: BULANAN — ringkasan per siswa (hadir/terlambat/alpha) + detail tiap hari
// ══════════════════════════════════════════════════════════════════════════
$bulan = $_GET['bulan'] ?? date('Y-m');

// Ringkasan
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
$ringkasan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Detail harian tiap siswa dalam bulan (untuk CSV kolom jam)
$stmt = $db->prepare('
    SELECT
        s.nis,
        a.tanggal,
        a.waktu_masuk,  a.status_masuk,
        a.waktu_pulang, a.status_pulang
    FROM siswa s
    LEFT JOIN absensi a
        ON s.id = a.siswa_id AND DATE_FORMAT(a.tanggal,"%Y-%m") = ?
    WHERE s.kelas = ? AND s.aktif = 1 AND a.tanggal IS NOT NULL
    ORDER BY s.nama, a.tanggal
');
$stmt->bind_param('ss', $bulan, $kelas);
$stmt->execute();
$detailRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

// Kelompokkan detail per NIS
$detailMap = [];
foreach ($detailRows as $d) {
    $detailMap[$d['nis']][$d['tanggal']] = [
        'waktu_masuk'   => $d['waktu_masuk'],
        'status_masuk'  => $d['status_masuk'],
        'waktu_pulang'  => $d['waktu_pulang'],
        'status_pulang' => $d['status_pulang'],
    ];
}

if ($format === 'csv') {
    $namaFile = "rekap_bulanan_{$kelas}_{$bulan}.csv";
    $namaFile = str_replace([' ', '/'], '_', $namaFile);
    _outputCsvBulanan($ringkasan, $detailMap, $namaFile, $kelas, $bulan);
}

jsonResponse(['mode' => 'bulanan', 'kelas' => $kelas, 'bulan' => $bulan, 'data' => $ringkasan]);


// ══════════════════════════════════════════════════════════════════════════
// HELPER: output CSV header
// ══════════════════════════════════════════════════════════════════════════
function _csvHeader(string $namaFile): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// ─── CSV HARIAN ───────────────────────────────────────────────────────────
function _outputCsvHarian(array $rows, string $namaFile, string $tanggal, string $kelas): void {
    _csvHeader($namaFile);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, ["REKAP ABSENSI HARIAN — Kelas: $kelas — Tanggal: $tanggal"]);
    fputcsv($out, []);
    fputcsv($out, ['No','NIS','Nama','Kelas','Status Masuk','Jam Masuk','Status Pulang','Jam Pulang']);

    $no = 1;
    $cHadir = $cTelat = $cAlpha = 0;
    foreach ($rows as $r) {
        $statusM = $r['status_masuk']  ?? 'alpha';
        $statusP = $r['status_pulang'] ?? '-';
        if ($statusM === 'hadir')     $cHadir++;
        elseif ($statusM === 'terlambat') $cTelat++;
        else $cAlpha++;

        fputcsv($out, [
            $no++,
            $r['nis'],
            $r['nama'],
            $r['kelas'],
            $statusM,
            $r['waktu_masuk']  ? substr($r['waktu_masuk'],  0, 5) : '-',
            $statusP,
            $r['waktu_pulang'] ? substr($r['waktu_pulang'], 0, 5) : '-',
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['','','TOTAL','', "Hadir: $cHadir", '', "Terlambat: $cTelat", "Alpha: $cAlpha"]);
    fclose($out);
    exit;
}

// ─── CSV MINGGUAN ─────────────────────────────────────────────────────────
function _outputCsvMingguan(array $rows, string $namaFile, array $hariList, string $kelas, string $minggu): void {
    _csvHeader($namaFile);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    $namaHari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

    fputcsv($out, ["REKAP ABSENSI MINGGUAN — Kelas: $kelas — Minggu: $minggu"]);
    fputcsv($out, []);

    // Header: No, NIS, Nama, lalu per hari: Masuk | Pulang
    $header = ['No', 'NIS', 'Nama'];
    foreach ($hariList as $i => $tgl) {
        $label = $namaHari[$i] . ' ' . date('d/m', strtotime($tgl));
        $header[] = $label . ' — Jam Masuk';
        $header[] = $label . ' — Jam Pulang';
        $header[] = $label . ' — Status';
    }
    fputcsv($out, $header);

    $no = 1;
    foreach ($rows as $r) {
        $baris = [$no++, $r['nis'], $r['nama']];
        foreach ($hariList as $tgl) {
            $h = $r['hari'][$tgl] ?? [];
            $baris[] = isset($h['waktu_masuk'])  && $h['waktu_masuk']  ? substr($h['waktu_masuk'],  0, 5) : '-';
            $baris[] = isset($h['waktu_pulang']) && $h['waktu_pulang'] ? substr($h['waktu_pulang'], 0, 5) : '-';
            $baris[] = $h['status_masuk'] ?? 'alpha';
        }
        fputcsv($out, $baris);
    }

    fclose($out);
    exit;
}

// ─── CSV BULANAN ─────────────────────────────────────────────────────────
function _outputCsvBulanan(array $ringkasan, array $detailMap, string $namaFile, string $kelas, string $bulan): void {
    _csvHeader($namaFile);
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, ["REKAP ABSENSI BULANAN — Kelas: $kelas — Bulan: $bulan"]);
    fputcsv($out, []);

    // ── Bagian 1: Ringkasan ──
    fputcsv($out, ['=== RINGKASAN ===']);
    fputcsv($out, ['No','NIS','Nama','Kelas','Hadir','Terlambat','Alpha','Total Hadir','Total Pulang']);

    $no = 1;
    foreach ($ringkasan as $r) {
        fputcsv($out, [
            $no++,
            $r['nis'],
            $r['nama'],
            $r['kelas'],
            $r['hadir']        ?? 0,
            $r['terlambat']    ?? 0,
            $r['alpha']        ?? 0,
            $r['total_hadir']  ?? 0,
            $r['total_pulang'] ?? 0,
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, [
        '','','TOTAL','',
        array_sum(array_column($ringkasan, 'hadir')),
        array_sum(array_column($ringkasan, 'terlambat')),
        array_sum(array_column($ringkasan, 'alpha')),
        '','',
    ]);

    // ── Bagian 2: Detail jam per tanggal ──
    fputcsv($out, []);
    fputcsv($out, ['=== DETAIL JAM MASUK & PULANG ===']);
    fputcsv($out, ['NIS','Nama','Tanggal','Hari','Status Masuk','Jam Masuk','Status Pulang','Jam Pulang']);

    $namaHariArr = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

    foreach ($ringkasan as $r) {
        $nis    = $r['nis'];
        $detail = $detailMap[$nis] ?? [];
        ksort($detail);
        foreach ($detail as $tgl => $d) {
            $hariIdx = (int)date('w', strtotime($tgl));
            fputcsv($out, [
                $nis,
                $r['nama'],
                date('d/m/Y', strtotime($tgl)),
                $namaHariArr[$hariIdx],
                $d['status_masuk']  ?? '-',
                $d['waktu_masuk']   ? substr($d['waktu_masuk'],  0, 5) : '-',
                $d['status_pulang'] ?? '-',
                $d['waktu_pulang']  ? substr($d['waktu_pulang'], 0, 5) : '-',
            ]);
        }
    }

    fclose($out);
    exit;
}