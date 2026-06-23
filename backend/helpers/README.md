# 🛠️ Helpers Documentation

Helper classes untuk menjaga kode tetap DRY dan maintainable.

## 📦 Tersedia

### **1. Response.php** — API Response Formatter
Standardize semua response JSON dengan format konsisten.

**Methods:**
```php
Response::json($data, $code = 200)              // Raw JSON response
Response::success($data, $message)              // Success response
Response::error($message, $code = 400)          // Error response
Response::validationError($errors)              // 422 validation
Response::unauthorized($message)                // 401
Response::notFound($message)                    // 404
Response::methodNotAllowed($message)            // 405
Response::serverError($message)                 // 500
```

**Example:**
```php
Response::success(['id' => 1], 'User created');
// Output: {"success":true,"message":"User created","data":{"id":1}}

Response::error('Invalid email', 422);
// Output: {"error":"Invalid email"}
```

---

### **2. Validator.php** — Input Validation
Validate input dengan rule-based system.

**Rules:**
```php
[
    'field_name' => [
        'required' => true,           // Wajib ada
        'min' => 3,                   // Min length
        'max' => 100,                 // Max length
        'regex' => '/pattern/',       // Custom regex
        'email' => true,              // Email format
        'numeric' => true,            // Must be number
        'in' => ['L', 'P'],           // Enum values
        'custom' => callable           // Custom validation
    ]
]
```

**Methods:**
```php
Validator::validate($data, $rules)       // Boolean: validate
Validator::errors()                      // Get errors array
Validator::validateOrFail($data, $rules) // Auto error response
Validator::sanitize($value, $type)       // Sanitize input
```

**Example:**
```php
$rules = [
    'nis' => ['required' => true, 'min' => 5, 'max' => 20],
    'nama' => ['required' => true, 'min' => 3],
    'email' => ['email' => true],
    'jk' => ['in' => ['L', 'P']]
];

Validator::validateOrFail($body, $rules);
// Akan auto response dengan error jika ada yang invalid

// Atau manual:
if (!Validator::validate($body, $rules)) {
    Response::validationError(Validator::errors());
}
```

---

### **3. Database.php** — Connection Singleton
Efficient database connection management (reuse connection).

**Methods:**
```php
Database::getInstance()                    // Get singleton instance
$db->getConnection()                       // Get raw mysqli
$db->prepare($query)                       // Prepare statement
$db->fetchOne($query, $params, $types)    // Fetch single row
$db->fetchAll($query, $params, $types)    // Fetch all rows
$db->execute($query, $params, $types)     // Execute INSERT/UPDATE/DELETE
$db->close()                               // Close connection (opsional)
```

**Example:**
```php
$db = Database::getInstance();

// GET single
$siswa = $db->fetchOne(
    'SELECT * FROM siswa WHERE id = ?',
    [$id],
    'i'
);

// GET multiple
$siswas = $db->fetchAll(
    'SELECT * FROM siswa WHERE kelas = ? ORDER BY nama',
    [$kelas],
    's'
);

// INSERT
$result = $db->execute(
    'INSERT INTO siswa (nis, nama, kelas) VALUES (?, ?, ?)',
    [$nis, $nama, $kelas],
    'sss'
);
echo $result['insertId'];  // New ID
echo $result['affected'];  // Rows affected
```

**Keuntungan:**
- ✅ Connection reused (tidak create baru setiap kali)
- ✅ Cleaner API (tidak perlu bind_param manual)
- ✅ Auto charset utf8mb4

---

### **4. Token.php** — JWT Authentication
Create & verify JWT tokens untuk authentication.

**Methods:**
```php
Token::create($payload)                  // Create token
Token::verify($token)                    // Verify & return payload
Token::getFromRequest()                  // Extract token dari header
Token::verifyOrFail()                    // Verify atau auto error response
Token::forUser($id, $username, $role, $hours) // Standard user token
```

**Example:**
```php
// Create token (login)
$token = Token::forUser($user['id'], $user['username'], $user['role'], 8);
// Returns: eyJhbGc... (JWT token)

// Verify token (protected endpoint)
$user = Token::verifyOrFail(); // Auto 401 response if invalid
// Returns: ['id' => 1, 'username' => 'admin', 'role' => 'admin', 'exp' => ...]

// Manual verify
$payload = Token::verify($tokenString);
if ($payload) {
    // Valid
} else {
    // Invalid or expired
}
```

**Token Expiry:**
- Default: 8 jam
- Customize: `Token::forUser($id, $username, $role, 24)` untuk 24 jam

---

## 🔐 Security Notes

1. **JWT_SECRET**: Set kuat di Railway environment variables
   ```
   JWT_SECRET=your_random_32_char_secret_xyz123...
   ```

2. **Validation**: Selalu validate input sebelum save ke database
   ```php
   Validator::validateOrFail($body, $rules);
   ```

3. **SQL Injection**: Prepared statements auto-protect (gunakan `?` placeholder)
   ```php
   $db->fetchOne('SELECT * FROM siswa WHERE id = ?', [$id], 'i');
   // Aman dari SQL injection
   ```

4. **Authentication**: Gunakan `Token::verifyOrFail()` untuk protect endpoint
   ```php
   $user = Token::verifyOrFail(); // 401 jika tidak valid
   ```

---

## 📝 Migration from Old Code

### **Before:**
```php
$db = getDB();
$stmt = $db->prepare('SELECT * FROM siswa WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();
jsonResponse(['success' => true, 'data' => $siswa]);
```

### **After:**
```php
$db = Database::getInstance();
$siswa = $db->fetchOne('SELECT * FROM siswa WHERE id = ?', [$id], 'i');
Response::success($siswa);
```

Much cleaner! ✨

---

## 🧪 Testing

Helpers bisa di-test terpisah tanpa perlu full integration:

```php
// Test Validator
$valid = Validator::validate(['email' => 'test@example.com'], ['email' => ['email' => true]]);
assert($valid === true);

// Test Token
$token = Token::create(['id' => 1, 'exp' => time() + 3600]);
$payload = Token::verify($token);
assert($payload['id'] === 1);
```

---

## 🚀 Best Practices

1. **Always Validate**: Gunakan Validator untuk setiap input
2. **Consistent Response**: Gunakan Response class untuk semua response
3. **Reuse Connection**: Pakai `Database::getInstance()` (singleton)
4. **Centralize Logic**: Jangan copy-paste, refactor jadi helper
5. **Document APIs**: Kasih docblock di setiap endpoint

Sudah jelas? Tanya jika ada yang perlu dijelaskan! 😊
