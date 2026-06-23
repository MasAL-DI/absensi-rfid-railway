# 📖 Panduan Refactoring Backend — Structure Baru

## 🎯 Struktur Baru yang Lebih Rapi

```
backend/
├── helpers/
│   ├── Response.php      ← Consistent API responses
│   ├── Validator.php     ← Input validation reusable  
│   ├── Database.php      ← DB connection (singleton)
│   ├── Token.php         ← JWT token handling
│   └── README.md         ← Dokumentasi helpers
├── api/
│   ├── login.php         ← ✅ Sudah refactor (contoh)
│   ├── siswa.php         ← ✅ Sudah refactor (contoh)
│   ├── dashboard.php     ← ✅ Sudah refactor (contoh)
│   ├── absensi.php       ← Perlu refactor
│   ├── izin_keluar.php   ← Perlu refactor
│   ├── sesi.php          ← Perlu refactor
│   └── rekap.php         ← Perlu refactor
├── config.php            ← ✅ Sudah refactor (load helpers & backward compat)
└── index.php             ← (opsional) Router utama di masa depan
```

---

## 🔄 Cara Menggunakan Helper Baru

### **1. Response Helper**
```php
// ❌ Sebelum (old):
jsonResponse(['error' => 'Username wajib'], 400);
echo json_encode(['success' => true]);

// ✅ Sesudah (new):
Response::error('Username wajib', 400);
Response::success($data, 'Data saved');
Response::validationError(['field' => 'error message']);
Response::unauthorized('Token expired');
Response::notFound('Siswa tidak ditemukan');
```

### **2. Validator Helper**
```php
// ❌ Sebelum (old):
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');
if (!$username || !$password) {
    jsonResponse(['error' => 'Username dan password wajib'], 400);
}

// ✅ Sesudah (new):
$rules = [
    'username' => ['required' => true, 'min' => 3, 'max' => 50],
    'password' => ['required' => true, 'min' => 6]
];

Validator::validateOrFail($body, $rules); // Auto response if invalid
// Atau manual:
if (!Validator::validate($body, $rules)) {
    Response::validationError(Validator::errors());
}
```

### **3. Database Helper**
```php
// ❌ Sebelum (old):
$db = getDB();
$stmt = $db->prepare('SELECT * FROM siswa WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

// ✅ Sesudah (new):
$db = Database::getInstance();
$siswa = $db->fetchOne('SELECT * FROM siswa WHERE id = ?', [$id], 'i');
// Atau untuk multiple rows:
$siswas = $db->fetchAll('SELECT * FROM siswa WHERE kelas = ?', [$kelas], 's');
// Untuk insert/update/delete:
$result = $db->execute('INSERT INTO siswa (nis, nama) VALUES (?, ?)', [$nis, $nama], 'ss');
echo $result['insertId'];
echo $result['affected'];
```

### **4. Token Helper**
```php
// ❌ Sebelum (old):
$token = buatToken(['id' => $user['id'], 'exp' => time() + 28800]);
$user = verifikasiToken($token);
cekAuth();

// ✅ Sesudah (new):
$token = Token::forUser($userId, $username, $role, 8); // 8 hours
$user = Token::verify($token);
$user = Token::verifyOrFail(); // Auto response if invalid
```

---

## 📋 Template untuk Refactor Endpoint

Setiap endpoint harus punya struktur seperti ini:

```php
<?php

/**
 * METHOD /api/endpoint.php
 * Deskripsi singkat apa yang endpoint ini lakukan
 * Query params: (jika ada)
 * Request body: (jika ada)
 * Response: struktur response
 */

require_once '../config.php';

// ===== 1. Check HTTP method =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

// ===== 2. Verify authentication (jika perlu) =====
$user = Token::verifyOrFail();

// ===== 3. Get & validate input =====
$body = getBody();
$rules = [
    'field1' => ['required' => true, 'min' => 1],
    'field2' => ['required' => false, 'max' => 100]
];
Validator::validateOrFail($body, $rules);

// ===== 4. Extract data =====
$field1 = trim($body['field1']);
$field2 = trim($body['field2'] ?? '');

// ===== 5. Business logic =====
$db = Database::getInstance();
$data = $db->fetchOne('SELECT * FROM table WHERE id = ?', [$id], 'i');

if (!$data) {
    Response::notFound('Data tidak ditemukan');
}

// Modify data...
$result = $db->execute('UPDATE table SET field = ? WHERE id = ?', [$value, $id], 'si');

// ===== 6. Response =====
Response::success($data, 'Operasi berhasil');
```

---

## ✅ Checklist: Refactor Endpoint

Untuk setiap endpoint yang ingin direfactor:

- [ ] Hapus `$db = getDB()` di awal, ganti dengan `$db = Database::getInstance()`
- [ ] Ganti `jsonResponse()` dengan `Response::success()` atau `Response::error()`
- [ ] Tambah `Validator` untuk input validation
- [ ] Ganti `cekAuth()` dengan `Token::verifyOrFail()`
- [ ] Ganti `buatToken()` dengan `Token::forUser()`
- [ ] Ganti manual `$stmt->prepare()` dengan `$db->fetchOne()` atau `$db->fetchAll()`
- [ ] Hapus `$db->close()` di akhir (tidak perlu lagi)
- [ ] Tambah docblock di atas endpoint
- [ ] Test endpoint dengan Postman/curl

---

## 🔍 Contoh Refactor: `absensi.php` (POST endpoint)

### Sebelum (Old):
```php
if ($method === 'POST') {
    $body = getBody();
    $uid = trim($body['uid'] ?? '');
    if (!$uid) jsonResponse(['status' => 'error', 'pesan' => 'UID wajib'], 400);
    
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM siswa WHERE uid_rfid = ? AND aktif = 1');
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$siswa) {
        $db->close();
        jsonResponse(['status' => 'tidak_terdaftar', 'pesan' => 'Kartu tidak terdaftar']);
    }
    
    // ... more logic ...
}
```

### Sesudah (New):
```php
if ($method === 'POST') {
    $body = getBody();
    
    $rules = [
        'uid' => ['required' => true, 'min' => 1, 'max' => 30],
        'kelas' => ['required' => false, 'max' => 10],
        'device_id' => ['required' => false, 'max' => 50]
    ];
    Validator::validateOrFail($body, $rules);
    
    $uid = trim($body['uid']);
    $db = Database::getInstance();
    
    $siswa = $db->fetchOne(
        'SELECT * FROM siswa WHERE uid_rfid = ? AND aktif = 1',
        [$uid],
        's'
    );
    
    if (!$siswa) {
        Response::notFound('Kartu tidak terdaftar');
    }
    
    // ... more logic ...
}
```

---

## 🚀 Keuntungan Struktur Baru

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| **Kode Clean** | Banyak duplikasi | DRY (Don't Repeat Yourself) |
| **Readability** | Verbose, banyak detail | Fokus pada logic, not infrastructure |
| **Maintenance** | Sulit refactor | Mudah, consistent pattern |
| **Error Handling** | Manual di setiap endpoint | Centralized & consistent |
| **Testing** | Sulit di-unit test | Helper bisa di-unit test terpisah |
| **Scalability** | Sulit grow | Mudah tambah endpoint baru |

---

## 📝 TODO: Refactor Endpoint Lainnya

- [ ] `absensi.php` - Complex POST logic, rate limiting
- [ ] `izin_keluar.php` - Multiple operations (GET, POST, DELETE)
- [ ] `sesi.php` - Device management, session logic
- [ ] `rekap.php` - Complex queries, export logic

Setiap endpoint bisa direfactor step-by-step tanpa mengubah API contract.

---

## 💬 Tips

1. **Backward Compatibility**: Legacy functions (`jsonResponse`, `cekAuth`, etc) masih ada di `config.php`, jadi endpoint lama tetap jalan
2. **Gradual Migration**: Refactor endpoint satu persatu, tidak perlu sekaligus
3. **Testing**: Test di Postman/curl sebelum & sesudah refactor untuk pastikan behavior sama
4. **Code Review**: Minta rekan review untuk pastikan consistency

Happy refactoring! 🎉
