<?php
include '../lib/db.php';  // Обратите внимание на путь к файлу db.php

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT health, max_health FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats) {
    echo json_encode(['error' => 'User not found']);
    exit();
}

echo json_encode([
    'health' => round($stats['health']),
    'max_health' => round($stats['max_health'])
]);
?>
