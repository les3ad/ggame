<?php
include 'db.php';

// Функция для проверки уровня профессии игрока
function get_player_profession_level($user_id, $profession_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT level FROM player_professions 
        WHERE player_id = :player_id AND profession_id = :profession_id
    ");
    $stmt->execute(['player_id' => $user_id, 'profession_id' => $profession_id]);
    return $stmt->fetchColumn();
}

// Функция для получения ресурсов на текущей позиции игрока с учётом уровня профессии
function get_resources_at_position($x, $y, $user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT r.*, p.name AS profession_name, p.id AS profession_id, rp.required_level
        FROM resources r
        LEFT JOIN resource_profession rp ON r.id = rp.resource_id
        LEFT JOIN professions p ON rp.profession_id = p.id
        WHERE r.x = :x AND r.y = :y AND (r.is_collected = 0 OR (r.is_collected = 1 AND TIMESTAMPDIFF(SECOND, r.collection_time, NOW()) >= r.respawn_time))
    ");
    $stmt->execute(['x' => $x, 'y' => $y]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resources as &$resource) {
        if ($resource['profession_id']) {
            $player_profession_level = get_player_profession_level($user_id, $resource['profession_id']);
            $resource['can_collect'] = ($player_profession_level >= $resource['required_level']);
        } else {
            $resource['can_collect'] = true;
        }
    }

    return $resources;
}


// Функция для сбора ресурса и начисления опыта профессии
function collect_resource($user_id, $resource_id) {
    global $pdo;

    // Проверяем, что ресурс ещё не собран
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id AND is_collected = 0");
    $stmt->execute(['id' => $resource_id]);
    $resource = $stmt->fetch();

    if ($resource) {
        // Проверяем, нужна ли профессия для сбора ресурса
        $stmt = $pdo->prepare("
            SELECT profession_id, required_level FROM resource_profession 
            WHERE resource_id = :resource_id
        ");
        $stmt->execute(['resource_id' => $resource_id]);
        $resource_profession = $stmt->fetch();

        if ($resource_profession) {
            $player_profession_level = get_player_profession_level($user_id, $resource_profession['profession_id']);
            if ($player_profession_level < $resource_profession['required_level']) {
                return ['success' => false, 'message' => 'Недостаточный уровень профессии для сбора этого ресурса.'];
            }

            // Начисляем опыт профессии
            $stmt = $pdo->prepare("
                UPDATE player_professions 
                SET experience = experience + 10 
                WHERE player_id = :player_id AND profession_id = :profession_id
            ");
            $stmt->execute([
                'player_id' => $user_id,
                'profession_id' => $resource_profession['profession_id']
            ]);
        }

        // Помечаем ресурс как собранный
        $stmt = $pdo->prepare("UPDATE resources SET is_collected = 1, collection_time = NOW() WHERE id = :id");
        $stmt->execute(['id' => $resource_id]);

        // Добавляем ресурс в инвентарь игрока
        $stmt = $pdo->prepare("
            INSERT INTO inventory (user_id, resource_id, quantity) 
            VALUES (:user_id, :resource_id, 1)
            ON DUPLICATE KEY UPDATE quantity = quantity + 1
        ");
        $stmt->execute(['user_id' => $user_id, 'resource_id' => $resource_id]);

        return ['success' => true, 'message' => 'Ресурс успешно собран!'];
    } else {
        return ['success' => false, 'message' => 'Ресурс уже был собран.'];
    }
}
?>
