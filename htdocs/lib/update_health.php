<?php
include 'player_stats.php';
session_start();

$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Пользователь не авторизован']));
}

// Для отладки
echo "ID пользователя: " . $_SESSION['user_id'];

// Получаем параметры игрока
$player_stats = get_player_stats($user_id);

// Проверяем, не находится ли игрок в бою
if ($player_stats['in_battle']) {
    die(json_encode(['success' => false, 'message' => 'Вы в бою, регенерация невозможна']));
}

// Регенерация здоровья
$player_stats = regenerate_health($player_stats);

// Вывод текущего здоровья для отладки
echo json_encode(['success' => true, 'health' => $player_stats['health']]);
?>
