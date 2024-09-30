<?php
include '../lib/auth.php';
include '../lib/db.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$new_x = $data['x'];
$new_y = $data['y'];
$user_id = $_SESSION['user_id'];

// Обновляем координаты игрока в базе данных
$stmt = $pdo->prepare("UPDATE users SET x = :x, y = :y WHERE id = :id");
$stmt->execute(['x' => $new_x, 'y' => $new_y, 'id' => $user_id]);

// Получаем обновленные данные игрока
$stmt = $pdo->prepare("SELECT health, x, y FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$playerData = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'player' => $playerData]);

?>