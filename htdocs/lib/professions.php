<?php
include 'db.php';

// Функция для получения профессий игрока
function get_player_professions($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT pp.profession_id, pp.level, pp.experience, p.name 
        FROM player_professions pp
        JOIN professions p ON pp.profession_id = p.id
        WHERE pp.player_id = :player_id
    ");
    $stmt->execute(['player_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для создания профессий для нового игрока (инициализация)
function initialize_player_professions($user_id) {
    global $pdo;

    // Получаем список всех профессий
    $stmt = $pdo->query("SELECT id FROM professions");
    $professions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Для каждой профессии добавляем запись с начальным уровнем 1 и 0 опыта
    foreach ($professions as $profession) {
        $stmt = $pdo->prepare("
            INSERT INTO player_professions (player_id, profession_id, level, experience) 
            VALUES (:player_id, :profession_id, 1, 0)
        ");
        $stmt->execute([
            'player_id' => $user_id,
            'profession_id' => $profession['id']
        ]);
    }
}

// Функция для инициализации отсутствующих профессий для игрока
function initialize_missing_professions($user_id) {
    global $pdo;

    // Получаем все доступные профессии
    $stmt = $pdo->query("SELECT id FROM professions");
    $all_professions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем все профессии, которые есть у игрока
    $stmt = $pdo->prepare("SELECT profession_id FROM player_professions WHERE player_id = :player_id");
    $stmt->execute(['player_id' => $user_id]);
    $player_professions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Для каждой профессии проверяем, есть ли она у игрока, если нет — добавляем
    foreach ($all_professions as $profession) {
        if (!in_array($profession['id'], $player_professions)) {
            // Добавляем профессию с 1 уровнем и 0 опыта
            $stmt = $pdo->prepare("
                INSERT INTO player_professions (player_id, profession_id, level, experience) 
                VALUES (:player_id, :profession_id, 1, 0)
            ");
            $stmt->execute([
                'player_id' => $user_id,
                'profession_id' => $profession['id']
            ]);
        }
    }
}

// Проверяем, существует ли функция перед объявлением
if (!function_exists('get_player_profession_level')) {
    // Функция для получения текущего уровня профессии игрока
    function get_player_profession_level($user_id, $profession_id) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT level, experience FROM player_professions 
            WHERE player_id = :player_id AND profession_id = :profession_id
        ");
        $stmt->execute(['player_id' => $user_id, 'profession_id' => $profession_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Функция для обновления опыта профессии
function add_profession_experience($user_id, $profession_id, $resource_level) {
    global $pdo;

    // Получаем текущий уровень и опыт профессии
    $stmt = $pdo->prepare("
        SELECT level, experience FROM player_professions 
        WHERE player_id = :player_id AND profession_id = :profession_id
    ");
    $stmt->execute(['player_id' => $user_id, 'profession_id' => $profession_id]);
    $profession = $stmt->fetch();

    // Определяем количество опыта за сбор ресурса (зависит от уровня ресурса)
    $experience_gain = $resource_level * 10; // Можно адаптировать под свою логику

    // Обновляем опыт профессии
    $new_experience = $profession['experience'] + $experience_gain;

    // Проверяем, достаточно ли опыта для повышения уровня
    $next_level_exp = get_required_experience_for_profession_level($profession['level']);
    if ($new_experience >= $next_level_exp) {
        // Повышаем уровень и обнуляем опыт для нового уровня
        $stmt = $pdo->prepare("
            UPDATE player_professions 
            SET level = level + 1, experience = :experience 
            WHERE player_id = :player_id AND profession_id = :profession_id
        ");
        $stmt->execute([
            'experience' => $new_experience - $next_level_exp,
            'player_id' => $user_id,
            'profession_id' => $profession_id
        ]);
    } else {
        // Только обновляем опыт
        $stmt = $pdo->prepare("
            UPDATE player_professions 
            SET experience = :experience 
            WHERE player_id = :player_id AND profession_id = :profession_id
        ");
        $stmt->execute([
            'experience' => $new_experience,
            'player_id' => $user_id,
            'profession_id' => $profession_id
        ]);
    }
}

// Функция для получения необходимого опыта для следующего уровня профессии
function get_required_experience_for_profession_level($level) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT required_experience FROM profession_experience_levels WHERE level = :level");
    $stmt->execute(['level' => $level]);
    return $stmt->fetchColumn();
}

// Функция для повышения уровня профессии с учётом новой системы опыта
function level_up_profession($user_id, $profession_id) {
    global $pdo;

    // Получаем текущий уровень и опыт профессии
    $stmt = $pdo->prepare("
        SELECT level, experience 
        FROM player_professions 
        WHERE player_id = :player_id AND profession_id = :profession_id
    ");
    $stmt->execute(['player_id' => $user_id, 'profession_id' => $profession_id]);
    $profession = $stmt->fetch();

    // Определяем опыт, необходимый для повышения уровня
    $experience_needed = get_required_experience_for_profession_level($profession['level']);

    // Если опыта достаточно для повышения уровня
    if ($profession['experience'] >= $experience_needed) {
        // Увеличиваем уровень и обновляем опыт
        $stmt = $pdo->prepare("
            UPDATE player_professions 
            SET level = level + 1, experience = experience - :experience_needed 
            WHERE player_id = :player_id AND profession_id = :profession_id
        ");
        $stmt->execute([
            'experience_needed' => $experience_needed,
            'player_id' => $user_id,
            'profession_id' => $profession_id
        ]);
    }
}
?>
