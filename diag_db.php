<?php
// Temporal diagnostic — delete after use
$dsn = 'mysql:host=localhost;dbname=carolinamoradb;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "=== Connected OK ===\n";
} catch (Exception $e) {
    // Try common DB names
    try {
        $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        echo "Available databases:\n" . implode(', ', $dbs) . "\n";
        die();
    } catch (Exception $e2) {
        die('Cannot connect: ' . $e2->getMessage() . "\n");
    }
}

// Tables
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n\n";

// Users
$rows = $pdo->query('SELECT user_id, first_name, last_name, account_status FROM `user` LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
echo "=== user table ===\n";
foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";

// professional_profile
$rows2 = $pdo->query('SELECT * FROM professional_profile LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== professional_profile table ===\n";
foreach ($rows2 as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
if (empty($rows2)) echo "EMPTY!\n";

// Exact query
$sql = "SELECT pp.professional_profile_id, u.first_name, u.last_name, u.email,
               pp.operational_status, pp.public_biography
        FROM professional_profile pp
        JOIN user u ON pp.professional_profile_id = u.user_id
        WHERE u.account_status = 'ACTIVE'
        AND pp.operational_status = 'ACTIVE'
        ORDER BY u.first_name ASC";
$rows3 = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== findAllProfessionals() (filtered) ===\n";
if (empty($rows3)) {
    echo "EMPTY — no results with status filters!\n";
} else {
    foreach ($rows3 as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

// Without filters
$sql2 = "SELECT pp.professional_profile_id, u.first_name, u.last_name, u.account_status, pp.operational_status
         FROM professional_profile pp
         JOIN user u ON pp.professional_profile_id = u.user_id";
$rows4 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Without status filters ===\n";
if (empty($rows4)) {
    echo "EMPTY — JOIN itself returns nothing! Check foreign key.\n";
} else {
    foreach ($rows4 as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
