<?php
include '../lib/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT in_battle FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);

if ($status) {
    echo json_encode(['in_battle' => $status['in_battle']]);
} else {
    echo json_encode(['error' => 'User not found']);
}
?>
