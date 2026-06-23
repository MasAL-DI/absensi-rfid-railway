<?php
/**
 * COMPREHENSIVE LOGIN DEBUG
 * Test: Database connection, User query, Password verification, Token generation
 * 
 * Akses: https://api-railway.app/login-debug.php
 */

require_once './config.php';

$testPassword = 'admin123';
$testUsername = 'admin';
$debug = [];

// 1. Test Database Connection
try {
    $db = Database::getInstance();
    $debug['database'] = ['status' => 'Connected ✓', 'type' => 'MySQL'];
} catch (Exception $e) {
    $debug['database'] = ['status' => 'FAILED ✗', 'error' => $e->getMessage()];
}

// 2. Test User Query
if (isset($db)) {
    $user = $db->fetchOne(
        'SELECT id, username, nama, password, role, aktif FROM users WHERE username = ? AND aktif = 1',
        [$testUsername],
        's'
    );
    
    if ($user) {
        $debug['user_query'] = [
            'status' => 'User Found ✓',
            'id' => $user['id'],
            'username' => $user['username'],
            'nama' => $user['nama'],
            'role' => $user['role'],
            'aktif' => $user['aktif']
        ];
        
        // 3. Test Password Verification
        $passwordMatch = password_verify($testPassword, $user['password']);
        $debug['password_verify'] = [
            'status' => $passwordMatch ? 'MATCH ✓' : 'MISMATCH ✗',
            'test_password' => $testPassword,
            'hash_length' => strlen($user['password']),
            'hash_starts_with' => substr($user['password'], 0, 10)
        ];
        
        // 4. Test Token Generation
        if ($passwordMatch) {
            try {
                $token = Token::forUser($user['id'], $user['username'], $user['role']);
                $debug['token_generation'] = [
                    'status' => 'Generated ✓',
                    'token_length' => strlen($token),
                    'token_preview' => substr($token, 0, 50) . '...'
                ];
                
                // 5. Test Token Verification
                try {
                    $verified = Token::verify($token);
                    $debug['token_verification'] = [
                        'status' => 'Verified ✓',
                        'payload' => $verified
                    ];
                } catch (Exception $e) {
                    $debug['token_verification'] = [
                        'status' => 'FAILED ✗',
                        'error' => $e->getMessage()
                    ];
                }
            } catch (Exception $e) {
                $debug['token_generation'] = [
                    'status' => 'FAILED ✗',
                    'error' => $e->getMessage()
                ];
            }
        }
        
    } else {
        $debug['user_query'] = [
            'status' => 'User NOT Found ✗',
            'searched_username' => $testUsername
        ];
    }
}

// 6. Test Environment Variables
$debug['environment'] = [
    'JWT_SECRET' => strlen(getenv('JWT_SECRET')) > 0 ? 'SET ✓' : 'NOT SET ✗',
    'JWT_SECRET_LENGTH' => strlen(getenv('JWT_SECRET')),
    'MySQL Host' => strlen(getenv('MYSQLHOST')) > 0 ? 'SET ✓' : 'NOT SET ✗',
    'MySQL User' => strlen(getenv('MYSQLUSER')) > 0 ? 'SET ✓' : 'NOT SET ✗'
];

// 7. Test PHP Settings
$debug['php_settings'] = [
    'PHP Version' => phpversion(),
    'password_hash available' => function_exists('password_hash') ? 'Yes ✓' : 'No ✗',
    'password_verify available' => function_exists('password_verify') ? 'Yes ✓' : 'No ✗'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Debug - RFID System</title>
    <style>
        * { margin: 0; padding: 0; }
        body { 
            font-family: 'Courier New', monospace; 
            background: linear-gradient(135deg, #1e1e1e 0%, #2d2d2d 100%);
            color: #00ff41;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { 
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            text-shadow: 0 0 10px rgba(0, 255, 65, 0.5);
        }
        .section {
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid #00ff41;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .section-title {
            background: #00ff41;
            color: #000;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 14px;
        }
        .section-content {
            padding: 15px;
        }
        .item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 255, 65, 0.2);
        }
        .item:last-child { border-bottom: none; }
        .label { color: #00ff41; font-weight: bold; min-width: 200px; }
        .value {
            color: #00ffff;
            word-break: break-all;
            text-align: right;
            flex: 1;
            margin-left: 20px;
        }
        .status-success { color: #00ff41; font-weight: bold; }
        .status-error { color: #ff0000; font-weight: bold; }
        .status-warning { color: #ffaa00; font-weight: bold; }
        pre {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #00ff41;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            border-top: 2px solid #00ff41;
            color: #00ff41;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Login Debug Console</h1>
        
        <?php foreach ($debug as $sectionName => $sectionData): ?>
            <div class="section">
                <div class="section-title"><?php echo ucfirst(str_replace('_', ' ', $sectionName)); ?></div>
                <div class="section-content">
                    <?php if (is_array($sectionData)): ?>
                        <?php foreach ($sectionData as $key => $value): ?>
                            <div class="item">
                                <span class="label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                <span class="value">
                                    <?php 
                                    if (is_bool($value)) {
                                        echo $value ? 'true' : 'false';
                                    } elseif (is_array($value)) {
                                        echo '<pre>' . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item">
                            <span class="value"><?php echo htmlspecialchars($sectionData); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="footer">
            <p>Test dilakukan pada: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Refresh halaman untuk test ulang</p>
        </div>
    </div>
</body>
</html>
