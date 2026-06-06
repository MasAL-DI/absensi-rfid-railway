CREATE DATABASE IF NOT EXISTS db_absensi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_absensi;

-- =============================================
-- TABEL KELAS
-- =============================================
CREATE TABLE IF NOT EXISTS kelas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  kode       VARCHAR(10)  NOT NULL UNIQUE,
  nama       VARCHAR(50)  NOT NULL,
  wali_kelas VARCHAR(100) DEFAULT '',
  aktif      TINYINT(1)   DEFAULT 1
);

INSERT INTO kelas (kode, nama) VALUES
  ('X-A',   'Kelas X-A'),   ('X-B',   'Kelas X-B'),   ('X-C',   'Kelas X-C'),
  ('XI-A',  'Kelas XI-A'),  ('XI-B',  'Kelas XI-B'),  ('XI-C',  'Kelas XI-C'),
  ('XII-A', 'Kelas XII-A'), ('XII-B', 'Kelas XII-B'), ('XII-C', 'Kelas XII-C')
ON DUPLICATE KEY UPDATE nama=VALUES(nama);

-- =============================================
-- TABEL SISWA
-- =============================================
CREATE TABLE IF NOT EXISTS siswa (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nis        VARCHAR(20)  NOT NULL UNIQUE,
  nama       VARCHAR(100) NOT NULL,
  kelas      VARCHAR(10)  NOT NULL,
  jk         ENUM('L','P') DEFAULT 'L',
  hp_ortu    VARCHAR(20)  DEFAULT '',
  uid_rfid   VARCHAR(30)  DEFAULT '' UNIQUE,
  aktif      TINYINT(1)   DEFAULT 1,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_kelas    (kelas),
  INDEX idx_uid_rfid (uid_rfid)
);

-- TABEL ABSENSI (masuk & pulang)

CREATE TABLE IF NOT EXISTS absensi (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  siswa_id     INT          NOT NULL,
  kelas        VARCHAR(10)  NOT NULL,
  tanggal      DATE         NOT NULL,
  -- TAP MASUK
  waktu_masuk  TIME         DEFAULT NULL,
  status_masuk ENUM('hadir','terlambat','alpha') DEFAULT 'alpha',
  -- TAP PULANG
  waktu_pulang TIME         DEFAULT NULL,
  status_pulang ENUM('tepat_waktu','lebih_awal','belum_pulang') DEFAULT 'belum_pulang',
  -- INFO
  uid_rfid     VARCHAR(30)  NOT NULL,
  keterangan   VARCHAR(255) DEFAULT '',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  UNIQUE KEY unique_absensi (siswa_id, tanggal),
  INDEX idx_tanggal (tanggal),
  INDEX idx_kelas   (kelas),
  INDEX idx_siswa   (siswa_id)
);

-- =============================================
-- TABEL IZIN KELUAR SEMENTARA
-- Dibuka oleh satpam, siswa tap keluar & kembali
-- =============================================
CREATE TABLE IF NOT EXISTS izin_keluar (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  siswa_id      INT          NOT NULL,
  tanggal       DATE         NOT NULL,
  -- Dibuka satpam
  dibuka_oleh   INT          NOT NULL,    -- user_id satpam/admin
  alasan        VARCHAR(255) DEFAULT '',
  -- Tap keluar
  jam_keluar    TIME         DEFAULT NULL,
  -- Tap kembali
  jam_kembali   TIME         DEFAULT NULL,
  -- Status
  status        ENUM('menunggu','keluar','kembali','expired') DEFAULT 'menunggu',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (siswa_id) REFERENCES siswa(id),
  INDEX idx_siswa_tgl (siswa_id, tanggal),
  INDEX idx_status    (status)
);

-- =============================================
-- TABEL JADWAL
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  hari         ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL UNIQUE,
  jam_masuk    TIME NOT NULL DEFAULT '07:00:00',
  batas_telat  TIME NOT NULL DEFAULT '07:30:00',
  jam_pulang   TIME NOT NULL DEFAULT '15:00:00'
);

INSERT INTO jadwal (hari, jam_masuk, batas_telat, jam_pulang) VALUES
  ('Senin',   '07:00:00', '07:30:00', '15:00:00'),
  ('Selasa',  '07:00:00', '07:30:00', '15:00:00'),
  ('Rabu',    '07:00:00', '07:30:00', '15:00:00'),
  ('Kamis',   '07:00:00', '07:30:00', '15:00:00'),
  ('Jumat',   '07:00:00', '07:30:00', '11:30:00'),
  ('Sabtu',   '07:00:00', '07:30:00', '13:00:00')
ON DUPLICATE KEY UPDATE jam_masuk=VALUES(jam_masuk);

-- =============================================
-- TABEL USERS (admin, guru, satpam)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  nama       VARCHAR(100) NOT NULL,
  role       ENUM('admin','guru','satpam') DEFAULT 'satpam',
  aktif      TINYINT(1)   DEFAULT 1,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Password default: admin123
INSERT INTO users (username, password, nama, role) VALUES
  ('admin',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
  ('satpam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Petugas Satpam', 'satpam')
ON DUPLICATE KEY UPDATE username=username;

-- =============================================
-- DATA SISWA CONTOH
-- =============================================
INSERT IGNORE INTO siswa (nis, nama, kelas, jk, uid_rfid) VALUES
  ('2024001', 'Andi Pratama',  'X-A', 'L', 'A1:B2:C3:D4'),
  ('2024002', 'Bella Kurnia',  'X-A', 'P', 'A2:B3:C4:D5'),
  ('2024003', 'Cahya Dewi',    'X-B', 'P', 'A3:B4:C5:D6'),
  ('2024004', 'Dimas Fauzan',  'X-B', 'L', 'A4:B5:C6:D7');

SELECT 'Database berhasil dibuat!' AS info;
SELECT COUNT(*) AS jumlah_siswa FROM siswa;
