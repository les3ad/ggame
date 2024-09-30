<?php
include '../lib/auth.php';
include '../lib/player_stats.php';
include '../lib/inventory.php';
include '../lib/professions.php';
include '../lib/quests.php'; // Подключаем файл квестов
include '../lib/db.php'; // Подключаем базу данных

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Инициализация отсутствующих профессий
initialize_missing_professions($user_id); // Функция, которая добавляет недостающие профессии

// Получаем характеристики игрока
$player_stats = get_player_stats($user_id);

// Проверяем, нужно ли обновить уровень игрока
update_player_level($user_id); // Функция для обновления уровня игрока

// Получаем текущий и следующий уровни опыта
$current_level_exp = get_required_experience_for_level($player_stats['level']);
$next_level_exp = get_required_experience_for_level($player_stats['level'] + 1);

// Текущий прогресс опыта (между уровнями)
$current_exp_in_level = $player_stats['exp'] - $current_level_exp;

// Получаем инвентарь игрока
$inventory = get_player_inventory($user_id);

// Получаем профессии игрока
$professions = get_player_professions($user_id);

// Получаем активные квесты игрока
$active_quests = get_active_quests_for_player($user_id);

// Получаем города для квестов
function get_city_name_by_id($city_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM cities WHERE id = ?");
    $stmt->execute([$city_id]);
    return $stmt->fetchColumn();
}

// Обработка выброса ресурса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_resource_id']) && isset($_POST['drop_quantity'])) {
    $resource_id = $_POST['drop_resource_id'];
    $quantity = $_POST['drop_quantity'];
    drop_resource($user_id, $resource_id, $quantity);
    header('Location: player_profile.php');
    exit();
}

// Передаем данные в HTML
include 'profile.html';
