<?php
include '../lib/db.php';
session_start();

$user_id = $_SESSION['user_id'];

// Получаем координаты игрока
$stmt = $pdo->prepare("SELECT x, y FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

$player_x = $user['x'];
$player_y = $user['y'];

// Получаем тайлы карты вокруг игрока
$stmt = $pdo->prepare("SELECT * FROM tiles WHERE x BETWEEN :minX AND :maxX AND y BETWEEN :minY AND :maxY");
$stmt->execute([
    'minX' => $player_x - 2,
    'maxX' => $player_x + 2,
    'minY' => $player_y - 2,
    'maxY' => $player_y + 2,
]);
$tiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем ресурсы на клетках
$stmt = $pdo->prepare("SELECT * FROM resources WHERE x BETWEEN :minX AND :maxX AND y BETWEEN :minY AND :maxY");
$stmt->execute([
    'minX' => $player_x - 2,
    'maxX' => $player_x + 2,
    'minY' => $player_y - 2,
    'maxY' => $player_y + 2,
]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Выводим данные в формате JSON
echo json_encode([
    'player_x' => $player_x,
    'player_y' => $player_y,
    'tiles' => $tiles,
    'resources' => $resources
]);
?>
