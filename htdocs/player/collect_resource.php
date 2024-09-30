<?php
include '../lib/auth.php';
include '../lib/db.php';
include '../lib/professions.php';  // Подключаем файл с функциями профессий

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$resource_id = $data['resource_id'];

// Получаем информацию о ресурсе
$stmt = $pdo->prepare("SELECT r.*, rp.profession_id, rp.required_level FROM resources r LEFT JOIN resource_profession rp ON r.id = rp.resource_id WHERE r.id = :resource_id");
$stmt->execute(['resource_id' => $resource_id]);
$resource = $stmt->fetch();

// Проверяем, существует ли такой ресурс
if (!$resource) {
    echo json_encode(['success' => false, 'message' => 'Ресурс не найден.']);
    exit();
}

// Проверяем уровень профессии игрока, если ресурс требует профессию
if ($resource['profession_id']) {
    $profession = get_player_profession_level($user_id, $resource['profession_id']);

    if ($profession['level'] < $resource['required_level']) {
        echo json_encode(['success' => false, 'message' => 'Недостаточный уровень профессии для сбора ресурса.']);
        exit();
    }

    // Начисляем опыт профессии
    add_profession_experience($user_id, $resource['profession_id'], $resource['level']);
}

// Сбор ресурса
$stmt = $pdo->prepare("UPDATE resources SET is_collected = 1, collection_time = NOW() WHERE id = :id");
$stmt->execute(['id' => $resource_id]);

// Добавляем ресурс в инвентарь игрока
$stmt = $pdo->prepare("INSERT INTO player_inventory (player_id, resource_id, quantity) VALUES (:player_id, :resource_id, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
$stmt->execute(['player_id' => $user_id, 'resource_id' => $resource_id]);

echo json_encode(['success' => true, 'message' => 'Ресурс успешно собран!']);
exit();
?>
