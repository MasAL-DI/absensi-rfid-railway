<?php

/**
 * DEBUG SCRIPT: Test Password Verification
 * 
 * Jalankan ini untuk debug mengapa login gagal
 * Akses: https://absensi-api.railway.app/password-debug.php?user=admin
 */

require_once './config.php';

$username = trim($_GET['user'] ?? 'admin');

$db = Database::getInstance();

// Get user dengan password field
$user = $db->fetchOne(
    'SELECT id, username, nama, password FROM users WHERE username = ?',
    [$username],
    's'
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Debug</title>
    <style>
        body { font-family: monospace; background: #222; color: #0f0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #0f0; }
        .info { background: #111; padding: 15px; margin: 10px 0; border-left: 3px solid #0f0; word-break: break-all; }
        .error { border-left-color: #f00; }
        .success { border-left-color: #0f0; }
        .label { color: #0f0; font-weight: bold; }
        input { background: #111; color: #0f0; border: 1px solid #0f0; padding: 5px; width: 100%; margin: 5px 0; }
        button { background: #0f0; color: #000; padding: 8px 15px; border: none; cursor: pointer; margin-top: 10px; }
        button:hover { background: #0f0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Verification Debug</h1>
        
        <div class="info">
            <div class="label">Username yang dicari:</div>
            <input type="text" value="<?php echo htmlspecialchars($username); ?>" readonly>
        </div>

        <?php if ($user): ?>
            <div class="info success">
                <div class="label">✓ User ditemukan di database</div>
            </div>

            <div class="info">
                <div class="label">ID:</div>
                <input type="text" value="<?php echo $user['id']; ?>" readonly>
            </div>

            <div class="info">
                <div class="label">Username:</div>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
            </div>

            <div class="info">
                <div class="label">Nama:</div>
                <input type="text" value="<?php echo htmlspecialchars($user['nama']); ?>" readonly>
            </div>

            <div class="info">
                <div class="label">Password Hash (dari database):</div>
                <textarea readonly style="width: 100%; height: 100px; background: #111; color: #0f0; border: 1px solid #0f0; padding: 5px; font-family: monospace;"><?php echo htmlspecialchars($user['password']); ?></textarea>
            </div>

            <div class="info">
                <div class="label">Length of hash:</div>
                <input type="text" value="<?php echo strlen($user['password']); ?>" readonly>
            </div>

            <!-- Test password verification -->
            <form method="POST" style="background: #111; padding: 15px; border: 1px solid #0f0; margin: 10px 0;">
                <div class="label">Test Password Verification:</div>
                <input type="password" name="test_password" placeholder="Masukkan password untuk di-test">
                <button type="submit">Test Password</button>
            </form>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_password'])): ?>
                <?php 
                $testPassword = $_POST['test_password'];
                $verified = password_verify($testPassword, $user['password']);
                ?>
                <div class="info <?php echo $verified ? 'success' : 'error'; ?>">
                    <div class="label">Test Result:</div>
                    <input type="text" value="<?php echo $verified ? '✓ PASSWORD COCOK!' : '✗ PASSWORD TIDAK COCOK'; ?>" readonly>
                </div>

                <div class="info">
                    <div class="label">Password yang di-test:</div>
                    <input type="text" value="<?php echo htmlspecialchars($testPassword); ?>" readonly>
                </div>

                <div class="info">
                    <div class="label">PHP password_verify() result:</div>
                    <input type="text" value="<?php echo $verified ? 'true' : 'false'; ?>" readonly>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="info error">
                <div class="label">✗ User tidak ditemukan di database!</div>
                <input type="text" value="Username '<?php echo htmlspecialchars($username); ?>' tidak ada" readonly>
            </div>
        <?php endif; ?>

        <div class="info" style="margin-top: 30px; border-left-color: #ff0;">
            <div class="label">💡 Tips Debug:</div>
            <div>
                1. Jika "User tidak ditemukan" → INSERT user dulu ke database<br>
                2. Jika "PASSWORD TIDAK COCOK" → Password hash salah, perlu UPDATE<br>
                3. Coba dengan password: <strong>admin123</strong>
            </div>
        </div>
    </div>
</body>
</html>
