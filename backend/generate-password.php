<?php
/**
 * Generate BCRYPT password hash untuk 'admin123'
 */

$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";
echo "\nQuery untuk UPDATE users:\n";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';\n";
?>
