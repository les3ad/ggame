<?php
include '../lib/auth.php';  
include '../lib/db.php';
include '../lib/quests.php';
include '../lib/player_stats.php';  
include '../lib/cities.php';  

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$player_stats = get_player_stats($user_id);

// Определяем город по координатам игрока
$city = get_city_at_position($player_stats['x'], $player_stats['y']);

if (!$city) {
    header('Location: game.php');
    exit();
}

// Получаем активные задания игрока
$active_quests = get_active_quests_for_player($user_id);

// Получаем выполненные задания игрока, чтобы исключить их из доступных
$completed_quests_stmt = $pdo->prepare("SELECT quest_id FROM player_quests WHERE player_id = ? AND (status = 'completed' OR status = 'finished')");
$completed_quests_stmt->execute([$user_id]);
$completed_quests = $completed_quests_stmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем доступные задания, исключая активные и завершенные квесты
// Получаем доступные задания, исключая активные и завершенные квесты
$available_quests = array_filter(get_quests_for_city($city['id'], $user_id), function($quest) use ($active_quests, $completed_quests) {
    // Проверяем активные квесты
    if (in_array($quest['id'], array_column($active_quests, 'quest_id'))) {
        return false; // Квест активен
    }
    // Проверяем завершенные квесты
    if (in_array($quest['id'], $completed_quests)) {
        return false; // Квест завершен
    }
    return true; // Квест доступен
});

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задания - <?php echo $city['name']; ?></title>
    <link rel="stylesheet" href="../css/quests.css">
    <script>
        function toggleQuestDetails(id) {
            var quest = document.getElementById('quest-' + id);
            quest.classList.toggle('active');
        }
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        h1, h2 {
            color: #333;
        }

        .quest-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 900px;
        }

        .quest-list {
            list-style-type: none;
            padding: 0;
        }

        .quest-list li {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .quest-list li:hover {
            background-color: #f0f0f0;
        }

        .quest-details {
            display: none;
            padding: 10px;
            background-color: #f4f4f4;
            border-radius: 8px;
            margin-top: 10px;
        }

        .quest-list li.active .quest-details {
            display: block;
        }

        .quest-requirements {
            margin-top: 10px;
        }

        .requirement-type {
            font-weight: bold;
            color: #666;
        }

        .return-button {
            text-align: center;
            margin-top: 20px;
        }

        .return-button a {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }

        .return-button a:hover {
            background-color: #45a049;
        }

        .quest-type {
            font-weight: bold;
            color: #007BFF;
        }

        .quest-list li a {
            display: inline-block;
            margin-top: 10px;
            background-color: #007BFF;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .quest-list li a:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Задания в городе <?php echo $city['name']; ?></h1>

    <div class="quest-container">
        <h2>Доступные задания</h2>
        <ul class="quest-list">
            <?php if (empty($available_quests)) { ?>
                <li>Нет доступных заданий в этом городе.</li>
            <?php } else { ?>
                <?php foreach ($available_quests as $quest) { ?>
                    <li id="quest-<?php echo $quest['id']; ?>" onclick="toggleQuestDetails(<?php echo $quest['id']; ?>)">
                        <strong><?php echo $quest['title']; ?></strong>
                        <div class="quest-details">
                            <p>Тип: <span class="quest-type"><?php echo $quest['is_daily'] ? 'Ежедневное' : 'Одноразовое'; ?></span></p>
                            <div class="quest-requirements">
                                <span class="requirement-type">Убить врагов:</span>
                                <ul>
                                    <?php
                                    $quest_enemies = get_quest_enemies($quest['id']);
                                    if (!empty($quest_enemies)) {
                                        foreach ($quest_enemies as $enemy) {
                                            echo "<li>Убить: {$enemy['quantity']} {$enemy['name']}</li>";
                                        }
                                    }
                                    ?>
                                </ul>

                                <span class="requirement-type">Собрать предметы:</span>
                                <ul>
                                    <?php
                                    $quest_items = get_quest_items($quest['id']);
                                    if (!empty($quest_items)) {
                                        foreach ($quest_items as $item) {
                                            echo "<li>Собрать: {$item['quantity']} {$item['name']}</li>";
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                            <a href="take_quest.php?quest_id=<?php echo $quest['id']; ?>">Взять задание</a>
                        </div>
                    </li>
                <?php } ?>
            <?php } ?>
        </ul>

        <h2>Активные задания</h2>
        <ul class="quest-list">
            <?php if (empty($active_quests)) { ?>
                <li>У вас нет активных заданий.</li>
            <?php } else { ?>
                <?php foreach ($active_quests as $active_quest) { ?>
                    <li id="quest-<?php echo $active_quest['quest_id']; ?>" onclick="toggleQuestDetails(<?php echo $active_quest['quest_id']; ?>)">
                        <strong><?php echo $active_quest['title']; ?></strong>
                        <div class="quest-details">
                            <p>Прогресс:</p>
                            <div class="quest-requirements">
                                <span class="requirement-type">Убить врагов:</span>
                                <ul>
                                    <?php
                                    $quest_enemies = get_quest_enemies($active_quest['quest_id']);
                                    if (!empty($quest_enemies)) {
                                        foreach ($quest_enemies as $enemy) {
                                            $killed = get_killed_enemies_for_quest($user_id, $active_quest['quest_id'], $enemy['enemy_id']);
                                            echo "<li>Убито: {$killed} из {$enemy['quantity']} {$enemy['name']}</li>";
                                        }
                                    }
                                    ?>
                                </ul>

                                <span class="requirement-type">Собрать предметы:</span>
                                <ul>
                                    <?php
                                    $quest_items = get_quest_items($active_quest['quest_id']);
                                    if (!empty($quest_items)) {
                                        foreach ($quest_items as $item) {
                                            $collected = get_collected_items_for_quest($user_id, $active_quest['quest_id'], $item['item_id']);
                                            echo "<li>Собрано: {$collected} из {$item['quantity']} {$item['name']}</li>";
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                            <?php if (is_quest_completed($active_quest['quest_id'], $user_id)) { ?>
                                <a href="complete_quest.php?quest_id=<?php echo $active_quest['quest_id']; ?>">Выполнить задание</a>
                            <?php } else { ?>
                                Задание еще не выполнено.
                            <?php } ?>
                        </div>
                    </li>
                <?php } ?>
            <?php } ?>
        </ul>

        <div class="return-button">
            <a href="game.php">Вернуться в игру</a>
        </div>
    </div>
</body>
</html>
