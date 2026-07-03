<?php
require_once __DIR__ . '/includes/db.php';

$name = 'Super Admin';
$username = 'superadmin';
$password = 'sadmin@123';
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("SELECT id FROM super_admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    echo "<h3>Super Admin already exists.</h3>";
    echo "<p>Username: <b>superadmin</b></p>";
    echo "<p>Please delete this file now: <b>create-super-admin-once.php</b></p>";
    exit;
}

$insert = $pdo->prepare("
    INSERT INTO super_admins (name, username, password, status)
    VALUES (?, ?, ?, 1)
");

$insert->execute([$name, $username, $passwordHash]);

echo "<h3>Super Admin created successfully.</h3>";
echo "<p>Username: <b>superadmin</b></p>";
echo "<p>Password: <b>sadmin@123</b></p>";
echo "<p style='color:red'><b>Important:</b> Delete this file immediately after use.</p>";
?>
