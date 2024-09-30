<?php
// Подключаем базу данных
include 'db.php';

// Получение характеристик игрока и уровня
function get_player_stats($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id, x, y, health, max_health, strength, defense, agility, dodge, luck, level, skill_points, last_health_update, in_battle, exp
        FROM users WHERE id = :id
    ");
    $stmt->execute(['id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        die('Пользователь не найден или произошла ошибка при получении данных!');
    }

    if ($stats['health'] < 0) {
        $stats['health'] = 0;
    }

    if (!isset($stats['skill_points']) || is_null($stats['skill_points'])) {
        $stats['skill_points'] = 0; // Устанавливаем значение по умолчанию
    }

    return $stats;
}
// Функция регенерации здоровья
function regenerate_health($stats) {
    if ($stats['in_battle']) {
        return $stats; // Не восстанавливаем здоровье, если игрок в бою
    }

    // Выводим текущее значение last_health_update
    echo "Значение last_health_update из базы: " . $stats['last_health_update'] . "\n";

    $current_time = time();
    $last_update_time = strtotime($stats['last_health_update']); // Преобразуем время
    $time_difference = $current_time - $last_update_time; // Разница во времени в секундах

    echo "Текущее время: " . $current_time . "\n";
    echo "Время последнего обновления: " . $last_update_time . "\n";
    echo "Прошло времени: " . $time_difference . " секунд\n";

    if ($time_difference < 60) {
        echo "Меньше 60 секунд прошло, регенерация не выполняется\n";
        return $stats;
    }

    // Базовая скорость регенерации — 1% от максимального здоровья за минуту
    $base_regen_rate_per_minute = 0.01;
    $defense_bonus = ($stats['defense'] / 10) * 0.005;
    $agility_bonus = ($stats['agility'] / 10) * 0.005;

    // Общая скорость регенерации за минуту
    $total_regen_rate_per_minute = $base_regen_rate_per_minute + $defense_bonus + $agility_bonus;

    // Количество восстанавливаемого здоровья
    $regen_amount = $stats['max_health'] * $total_regen_rate_per_minute * ($time_difference / 60); 

    // Ограничиваем восстановление максимальным здоровьем
    $new_health = min($stats['max_health'], $stats['health'] + $regen_amount);

    // Если здоровье изменилось, обновляем его в базе данных
    if ($new_health > $stats['health']) {
        $stats['health'] = $new_health;
        update_health($stats['id'], $stats['health']);
        update_last_health_update($stats['id']);
        echo "Здоровье восстановлено до: " . $new_health;
    } else {
        // Для отладки: выводим значение $regen_amount
        echo "Здоровье не увеличено. Восстановлено: " . $regen_amount;
    }

    return $stats;
}



function update_last_health_update($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users SET last_health_update = NOW() WHERE id = :id
    ");
    $stmt->execute(['id' => $user_id]);

    // Выполним проверку, что поле обновилось
    $stmt = $pdo->prepare("SELECT last_health_update FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $updated_time = $stmt->fetchColumn();

    echo "Поле last_health_update обновлено: " . $updated_time . "\n";
}



// Функция для обновления состояния "в бою"
function enter_battle($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users SET in_battle = 1, last_health_update = NOW() WHERE id = :id
    ");
    $stmt->execute(['id' => $user_id]);
}

function exit_battle($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users SET in_battle = 0, last_health_update = NOW() WHERE id = :id
    ");
    $stmt->execute(['id' => $user_id]);
}

// Функция для обновления здоровья игрока
function update_health($user_id, $new_health) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users 
        SET health = :health 
        WHERE id = :id
    ");
    $stmt->execute(['health' => $new_health, 'id' => $user_id]);
}

// Обновление характеристик и очков навыков
function update_player_stats($user_id, $stats) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users SET
        exp = :exp,
        health = :health,
        strength = :strength,
        defense = :defense,
        agility = :agility,
        dodge = :dodge,
        luck = :luck,
        skill_points = :skill_points
        WHERE id = :id
    ");
    return $stmt->execute([
        'exp' => $stats['exp'],
        'health' => $stats['health'],
        'strength' => $stats['strength'],
        'defense' => $stats['defense'],
        'agility' => $stats['agility'],
        'dodge' => $stats['dodge'],
        'luck' => $stats['luck'],
        'skill_points' => $stats['skill_points'],
        'id' => $user_id
    ]);
}

// Функция для получения необходимого опыта для текущего уровня
function get_required_experience_for_level($level) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT required_experience FROM experience_levels WHERE level = :level");
    $stmt->execute(['level' => $level]);
    return $stmt->fetchColumn();
}


// Функция для повышения уровня игрока
function level_up($user_id, $stats) {
    global $pdo;

    // Получаем текущий уровень игрока
    $new_level = $stats['level'] + 1;
    $new_skill_points = $stats['skill_points'] + 3;

    // Сброс опыта на остаток, чтобы опыт соответствовал новому уровню
    $required_exp = get_required_experience_for_level($new_level - 1); // Опыт, который требовался для текущего уровня
    $remaining_exp = $stats['exp'] - $required_exp;

    $stmt = $pdo->prepare("
        UPDATE users 
        SET level = :level, skill_points = :skill_points, exp = :remaining_exp
        WHERE id = :id
    ");
    $stmt->execute([
        'level' => $new_level,
        'skill_points' => $new_skill_points,
        'remaining_exp' => $remaining_exp,
        'id' => $user_id
    ]);
}


// Функция для восстановления здоровья и перемещения на координаты 5x5
function revive_player($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE users 
        SET x = 5, y = 5, health = max_health
        WHERE id = :id
    ");
    $stmt->execute(['id' => $user_id]);
}

// Функция для расчета уровня на основе текущего опыта
function calculate_level($current_exp) {
    global $pdo;

    // Получаем все уровни и их требования по опыту
    $stmt = $pdo->query("SELECT level, required_experience FROM experience_levels ORDER BY level ASC");
    $exp_table = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $level = 1;  // Уровень по умолчанию
    foreach ($exp_table as $row) {
        if ($current_exp >= $row['required_experience']) {
            $level = $row['level'];
        } else {
            break; // Останавливаемся, если текущий опыт меньше требуемого для следующего уровня
        }
    }

    return $level;
}

// Функция для обновления опыта игрока и проверки уровня
function add_experience($user_id, $experience) {
    global $pdo;

    // Получаем текущий опыт и уровень игрока
    $stmt = $pdo->prepare("SELECT exp, level FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    // Обновляем опыт игрока
    $new_exp = $player['exp'] + $experience;
    $stmt = $pdo->prepare("UPDATE users SET exp = :exp WHERE id = :id");
    $stmt->execute(['exp' => $new_exp, 'id' => $user_id]);

    // Проверяем, достиг ли игрок нового уровня
    $new_level = calculate_level($new_exp);
    if ($new_level > $player['level']) {
        // Повышаем уровень игрока
        level_up($user_id, ['level' => $player['level'], 'exp' => $new_exp, 'skill_points' => $player['skill_points']]);
        return "Поздравляем! Вы повысили уровень до {$new_level}!";
    }

    return "Ваш уровень остался на прежнем уровне: {$player['level']}.";
}

// Функция для обновления уровня игрока
function update_player_level($user_id) {
    global $pdo;

    // Получаем текущий опыт и уровень игрока
    $stmt = $pdo->prepare("SELECT exp, level FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    $current_exp = $player['exp'];
    $current_level = $player['level'];

    // Расчет нового уровня на основе опыта
    $new_level = calculate_level($current_exp);

    // Если новый уровень выше текущего, обновляем уровень и добавляем очки навыков
    if ($new_level > $current_level) {
        $skill_points_gain = ($new_level - $current_level) * 3;

        $stmt = $pdo->prepare("UPDATE users SET level = :level, skill_points = skill_points + :skill_points WHERE id = :id");
        $stmt->execute([
            'level' => $new_level,
            'skill_points' => $skill_points_gain,
            'id' => $user_id
        ]);

        return "Поздравляем! Вы повысили уровень до {$new_level}!";
    }

    return "Ваш уровень остался на прежнем уровне: {$current_level}.";
}
?>
