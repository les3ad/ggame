<?php
include 'db.php';

// Функция для удаления квестов со статусом 'finished', если прошло 12 часов
function delete_finished_quests($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        DELETE FROM player_quests
        WHERE player_id = ? AND status = 'finished'
        AND completed_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)
    ");
    $stmt->execute([$user_id]);
}

// Функция для проверки, завершён ли необходимый квест
function is_quest_dependency_completed($required_quest_id, $user_id) {
    global $pdo;

    // Проверяем, завершен ли требуемый квест
    $stmt = $pdo->prepare("
        SELECT status
        FROM player_quests 
        WHERE quest_id = ? 
        AND player_id = ?
    ");
    $stmt->execute([$required_quest_id, $user_id]);
    $status = $stmt->fetchColumn();

    // Если статус 'completed', то квест завершен
    return $status === 'completed';
}

// Функция для получения доступных квестов для города
function get_quests_for_city($city_id, $user_id) {
    global $pdo;

    // Удаляем квесты со статусом 'finished', если прошло 12 часов
    delete_finished_quests($user_id);

    // Получаем доступные квесты
    $stmt = $pdo->prepare("
        SELECT q.* 
        FROM quests q
        LEFT JOIN player_quests pq
        ON q.id = pq.quest_id 
        AND pq.player_id = ? 
        WHERE q.city_id = ? 
        AND (pq.status IS NULL OR pq.status NOT IN ('active', 'completed'))
    ");
    $stmt->execute([$user_id, $city_id]);
    $available_quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Фильтруем квесты, проверяя их зависимости
    $filtered_quests = array_filter($available_quests, function($quest) use ($user_id) {
        // Проверяем, есть ли у квеста зависимость
        if ($quest['required_quest_id']) {
            // Проверяем, завершен ли зависимый квест
            return is_quest_dependency_completed($quest['required_quest_id'], $user_id);
        }
        // Если зависимости нет, квест доступен
        return true;
    });

    return $filtered_quests;
}
// Получение активных квестов игрока с информацией о городе
function get_active_quests_with_city($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT pq.*, q.title, q.description, c.name as city_name
        FROM player_quests pq
        JOIN quests q ON pq.quest_id = q.id
        JOIN cities c ON q.city_id = c.id
        WHERE pq.player_id = ? AND pq.status = 'active'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для проверки, можно ли взять квест
function take_quest($quest_id, $user_id) {
    global $pdo;

    if (!can_take_daily_quest($user_id, $quest_id)) {
        die('Вы не можете взять этот квест. Попробуйте снова через 12 часов.');
    }

    // Добавляем запись о квесте в player_quests
    $stmt = $pdo->prepare("INSERT INTO player_quests (player_id, quest_id, status, progress) VALUES (?, ?, 'active', 0)");
    $stmt->execute([$user_id, $quest_id]);

    // Получаем всех врагов, которых нужно убить для этого квеста
    $quest_enemies = get_quest_enemies($quest_id);
    foreach ($quest_enemies as $enemy) {
        $stmt = $pdo->prepare("INSERT INTO player_quest_enemies (player_id, quest_id, enemy_id, quantity) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $quest_id, $enemy['enemy_id'], $enemy['quantity']]);
    }

    // Получаем все предметы, которые нужно собрать для этого квеста
    $quest_items = get_quest_items($quest_id);
    foreach ($quest_items as $item) {
        // Проверяем, сколько предметов уже есть в инвентаре
        $stmt = $pdo->prepare("SELECT quantity FROM player_inventory WHERE player_id = ? AND resource_id = ?");
        $stmt->execute([$user_id, $item['item_id']]);
        $inventory_quantity = $stmt->fetchColumn() ?: 0;

        // Вставляем начальные данные по квесту
        $collected = min($inventory_quantity, $item['quantity']);
        $stmt = $pdo->prepare("INSERT INTO player_quest_items (player_id, quest_id, item_id, collected, quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $quest_id, $item['item_id'], $collected, $item['quantity']]);
    }
}

// Функция для проверки, можно ли взять ежедневный квест
function can_take_daily_quest($user_id, $quest_id) {
    global $pdo;

    // Проверяем, был ли этот квест завершен ранее и имеет ли он статус 'finished'
    $stmt = $pdo->prepare("
        SELECT completed_at 
        FROM player_quests 
        WHERE player_id = ? 
        AND quest_id = ? 
        AND status = 'finished'
        AND completed_at IS NOT NULL
    ");
    $stmt->execute([$user_id, $quest_id]);
    $completed_at = $stmt->fetchColumn();

    if ($completed_at) {
        $now = new DateTime();
        $last_completed = new DateTime($completed_at);
        $interval = $now->diff($last_completed);

        // Проверяем, прошло ли 12 часов с момента завершения
        if ($interval->days == 0 && $interval->h < 12) {
            return false; // Если 12 часов еще не прошло, квест нельзя взять
        }
    }

    return true; // Можно взять квест снова
}

// Получение активных квестов игрока с информацией о городе
function get_active_quests_for_player($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT pq.*, q.title, q.description, q.city_id
        FROM player_quests pq
        JOIN quests q ON pq.quest_id = q.id
        WHERE pq.player_id = ? AND pq.status = 'active'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Получение врагов для квеста
function get_quest_enemies($quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.id as enemy_id, e.name, qe.quantity 
        FROM quest_enemies qe 
        JOIN enemies e ON qe.enemy_id = e.id 
        WHERE qe.quest_id = ?
    ");
    $stmt->execute([$quest_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение предметов для квеста
function get_quest_items($quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qi.item_id, qi.quantity, r.name 
        FROM quest_items qi 
        JOIN resources r ON qi.item_id = r.id 
        WHERE qi.quest_id = ?
    ");
    $stmt->execute([$quest_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Проверка выполнения квеста
function is_quest_completed($quest_id, $user_id) {
    global $pdo;

    // Проверка убитых врагов
    $stmt = $pdo->prepare("
        SELECT qe.enemy_id, qe.quantity, COALESCE(SUM(pke.killed), 0) as killed
        FROM quest_enemies qe
        LEFT JOIN player_killed_enemies pke ON qe.enemy_id = pke.enemy_id AND pke.player_id = :user_id
        WHERE qe.quest_id = :quest_id
        GROUP BY qe.enemy_id
        HAVING killed < qe.quantity
    ");
    $stmt->execute(['user_id' => $user_id, 'quest_id' => $quest_id]);
    $incomplete_enemies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($incomplete_enemies)) {
        return false;
    }

    // Проверка собранных предметов из инвентаря
    $stmt = $pdo->prepare("
        SELECT qi.item_id, qi.quantity, COALESCE(pi.quantity, 0) as collected
        FROM quest_items qi
        LEFT JOIN player_inventory pi ON qi.item_id = pi.resource_id AND pi.player_id = :user_id
        WHERE qi.quest_id = :quest_id
        GROUP BY qi.item_id
        HAVING collected < qi.quantity
    ");
    $stmt->execute(['user_id' => $user_id, 'quest_id' => $quest_id]);
    $incomplete_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($incomplete_items)) {
        return false;
    }

    return true;
}

// Получение прогресса по убитым врагам
function get_killed_enemies_for_quest($user_id, $quest_id, $enemy_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT killed 
        FROM player_killed_enemies 
        WHERE player_id = ? AND quest_id = ? AND enemy_id = ?
    ");
    $stmt->execute([$user_id, $quest_id, $enemy_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['killed'] : 0;
}

// Получение прогресса по собранным предметам
function get_collected_items_for_quest($user_id, $quest_id, $item_id) {
    global $pdo;

    // Сначала проверим, сколько предметов у игрока в инвентаре
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0) as collected 
        FROM player_inventory 
        WHERE player_id = ? AND resource_id = ?
    ");
    $stmt->execute([$user_id, $item_id]);
    $inventory_quantity = $stmt->fetchColumn();

    // Проверим, сколько предметов нужно для выполнения квеста
    $stmt = $pdo->prepare("
        SELECT quantity 
        FROM quest_items 
        WHERE quest_id = ? AND item_id = ?
    ");
    $stmt->execute([$quest_id, $item_id]);
    $required_quantity = $stmt->fetchColumn();

    // Возвращаем минимальное значение между тем, что собрано и тем, что требуется
    return min($inventory_quantity, $required_quantity);
}

// Функция для получения наград за квест
function get_quest_rewards($quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qr.item_id, qr.quantity, r.name 
        FROM quest_rewards qr
        JOIN resources r ON qr.item_id = r.id
        WHERE qr.quest_id = ?
    ");
    $stmt->execute([$quest_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для получения информации о квесте
function get_quest_info($quest_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM quests WHERE id = ?
    ");
    $stmt->execute([$quest_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

