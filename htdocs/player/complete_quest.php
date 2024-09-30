<?php
include '../lib/auth.php';
include '../lib/db.php';
include '../lib/quests.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quest_id = isset($_GET['quest_id']) ? (int)$_GET['quest_id'] : 0;

// Получаем информацию о квесте
$quest_info = get_quest_info($quest_id);

// Проверяем, выполнен ли квест
if (!is_quest_completed($quest_id, $user_id)) {
    header('Location: ../player/quests.php?error=quest_not_completed');
    exit();
}

// Если квест ежедневный (is_daily = 1), меняем его статус на 'finished'
if ($quest_info['is_daily'] == 1) {
    $stmt = $pdo->prepare("UPDATE player_quests SET status = 'finished', completed_at = NOW() WHERE player_id = ? AND quest_id = ?");
} else {
    // Для одноразовых квестов оставляем статус 'completed'
    $stmt = $pdo->prepare("UPDATE player_quests SET status = 'completed', completed_at = NOW() WHERE player_id = ? AND quest_id = ?");
}
$stmt->execute([$user_id, $quest_id]);



// Начисляем опыт, деньги и очки характеристик
if ($quest_info['experience_reward'] > 0) {
    add_experience($user_id, $quest_info['experience_reward']);
}
if ($quest_info['money_reward'] > 0) {
    add_money($user_id, $quest_info['money_reward']);
}
if ($quest_info['stat_point_reward'] > 0) {
    add_stat_points($user_id, $quest_info['stat_point_reward']);
}

// Получаем необходимые предметы для завершения квеста
$quest_items = get_quest_items($quest_id);
$insufficient_resources = false;

// Проверяем, достаточно ли ресурсов для необходимых предметов квеста
foreach ($quest_items as $item) {
    // Получаем текущее количество ресурса в инвентаре
    $stmt = $pdo->prepare("SELECT quantity FROM player_inventory WHERE player_id = ? AND resource_id = ?");
    $stmt->execute([$user_id, $item['item_id']]);
    $current_quantity = $stmt->fetchColumn();

    // Логируем информацию о ресурсе
    error_log("Checking item ID: {$item['item_id']}, Required: {$item['quantity']}, Current: {$current_quantity}");

    // Проверяем, достаточно ли ресурсов
    if ($current_quantity === false || $current_quantity < $item['quantity']) {
        $insufficient_resources = true; // Отмечаем, что ресурсов недостаточно
        break; // Прекращаем цикл, если ресурсов недостаточно
    }
}

// Если недостаточно ресурсов, выводим сообщение и останавливаем выполнение
if ($insufficient_resources) {
    die("Недостаточно ресурсов для выполнения квеста.");
}

// Если ресурсов достаточно, продолжаем с их списанием
foreach ($quest_items as $item) {
    // Списываем предметы из инвентаря
    $stmt = $pdo->prepare("UPDATE player_inventory SET quantity = quantity - ? WHERE player_id = ? AND resource_id = ?");
    $stmt->execute([$item['quantity'], $user_id, $item['item_id']]);
}

// Начисление наградных ресурсов
$rewards = get_quest_rewards($quest_id);
foreach ($rewards as $reward) {
    if ($reward['item_id']) {
        // Добавляем наградные ресурсы в инвентарь игрока
        $stmt = $pdo->prepare("INSERT INTO player_inventory (player_id, resource_id, quantity) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
        $stmt->execute([$user_id, $reward['item_id'], $reward['quantity']]);
    }
}

// Перенаправляем игрока на страницу с квестами с подтверждением завершения
header('Location: ../player/quests.php?success=quest_completed');
exit();

// Функции для начисления опыта, денег и очков характеристик
function add_experience($user_id, $experience) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET exp = exp + ? WHERE id = ?");
    $stmt->execute([$experience, $user_id]);
}

function add_money($user_id, $money) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET gold = gold + ? WHERE id = ?");
    $stmt->execute([$money, $user_id]);
}

function add_stat_points($user_id, $points) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET skill_points = skill_points + ? WHERE id = ?");
    $stmt->execute([$points, $user_id]);
}
