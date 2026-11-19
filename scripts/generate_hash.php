<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Hash untuk 'admin123':\n";
echo $hash . "\n\n";

// Update langsung ke database
require __DIR__ . '/../bootstrap/app.php';
use App\Core\Database;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = 'admin@rekanweb.biz.id'");
    $stmt->execute([$hash]);
    echo "Password admin berhasil diupdate!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
