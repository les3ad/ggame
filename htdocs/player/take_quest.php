<?php
include_once '../lib/auth.php';  // Используем include_once для предотвращения повторного подключения
include_once '../lib/db.php';
include_once '../lib/quests.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quest_id = isset($_GET['quest_id']) ? (int)$_GET['quest_id'] : 0;

// Проверим, что квест существует
$quest = get_quest_by_id($quest_id);
if (!$quest) {
    die('Квест не найден.');
}

// Проверим, можно ли взять квест (если это ежедневный квест)
if ($quest['is_daily'] && !can_take_daily_quest($user_id, $quest_id)) {
    $time_left = get_time_until_next_daily_quest($user_id, $quest_id);
    die("Вы не можете взять этот квест снова. $time_left");
}

// Проверим, не активен ли квест у игрока уже
if (has_active_quest($user_id, $quest_id)) {
    die('Этот квест уже находится в ваших активных заданиях.');
}

// Добавляем задание в активные
$stmt = $pdo->prepare("
    INSERT INTO player_quests (player_id, quest_id, status, progress, completed_at) 
    VALUES (?, ?, 'active', 0, NULL)
");
$stmt->execute([$user_id, $quest_id]);

header('Location: ../player/quests.php');
exit();

// Функция для получения информации о квесте
function get_quest_by_id($quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE id = ?");
    $stmt->execute([$quest_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Функция для проверки, есть ли квест уже в активных
function has_active_quest($user_id, $quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM player_quests WHERE player_id = ? AND quest_id = ? AND status = 'active'");
    $stmt->execute([$user_id, $quest_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}




// Время, оставшееся до возможности снова взять квест (для ежедневных квестов)
function get_time_until_next_daily_quest($user_id, $quest_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT completed_at 
        FROM player_quests 
        WHERE player_id = ? 
        AND quest_id = ? 
        AND status = 'completed'
        AND completed_at IS NOT NULL
    ");
    $stmt->execute([$user_id, $quest_id]);
    $completed_at = $stmt->fetchColumn();

    if ($completed_at) {
        $now = new DateTime();
        $last_completed = new DateTime($completed_at);
        $interval = $now->diff($last_completed);

        if ($interval->days == 0 && $interval->h < 12) {
            $hours_left = 11 - $interval->h;
            $minutes_left = 59 - $interval->i;
            return "$hours_left ч. $minutes_left мин. до следующего выполнения.";
        }
    }

    return 'Можно взять снова.';
}
?>
