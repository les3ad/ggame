

<?php
include '../lib/auth.php';
include '../lib/db.php';
include '../lib/enemies.php';
include '../lib/combat.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$enemy_id = $_GET['enemy_id'] ?? null;

if (!$enemy_id) {
    die("ID врага не передан.");
}

// Получаем параметры игрока
$player_stats = get_player_stats($user_id);

// Ставим статус "в бою" для игрока
$stmt = $pdo->prepare("UPDATE users SET in_battle = 1 WHERE id = :id");
$stmt->execute(['id' => $user_id]);

// Получаем врага из таблицы enemy_spawn_points
$stmt = $pdo->prepare("
    SELECT esp.*, e.name, e.damage, e.level, e.strength, e.defense, e.agility, e.dodge, e.luck, e.exp_reward
    FROM enemy_spawn_points esp
    JOIN enemies e ON esp.enemy_id = e.id
    WHERE esp.id = :enemy_id
");
$stmt->execute(['enemy_id' => $enemy_id]);
$enemy = $stmt->fetch();

// Проверка на существование врага
if (!$enemy) {
    die("Враг не найден.");
}

// Проверка, жив ли враг
if ($enemy['is_dead']) {
    die("Этот враг уже побежден.");
}

// Начинаем бой
$combat_log = '';
$battle_ended = false;
$show_leave_button = false;
$enemy_health = $enemy['health'];
$player_health = $player_stats['health'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка на таймаут
    if (isset($_POST['timeout']) && $_POST['timeout'] === 'true') {
        // Обрабатываем таймаут, игрок умирает и перемещается с 0 здоровьем
        $stmt = $pdo->prepare("UPDATE users SET x = 5, y = 5, health = 0, in_battle = 0 WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        echo json_encode(['success' => true, 'message' => 'Вы умерли из-за бездействия и перемещены на начальные координаты с 0 здоровьем.']);
        exit();
    }

    // Вызов функции атаки и получение результатов
    $attack_result = attack_enemy($user_id, $enemy_id);
    $combat_log = $attack_result['log'];
    $enemy_health = $attack_result['enemy_health'];
    $player_health = $attack_result['player_health'];

    if ($player_health <= 0) {
        $battle_ended = true;
        // Перемещение игрока на координаты (5, 5) с 0 здоровьем
        $stmt = $pdo->prepare("UPDATE users SET x = 5, y = 5, health = 0, in_battle = 0 WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
    } elseif ($enemy_health <= 0) {
        $battle_ended = true;
        $stmt = $pdo->prepare("UPDATE users SET in_battle = 0 WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $show_leave_button = true;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Бой с врагом</title>
    <link rel="stylesheet" href="../css/battle_styles.css">
    <style>
        .progress-bar {
            width: 100%;
            background-color: #ddd;
            border-radius: 10px;
        }

        .progress {
            width: 100%;
            height: 30px;
            background-color: green;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <h1>Бой с <?php echo htmlspecialchars($enemy['name']); ?></h1>

    <div class="combat-container">
        <div class="player-stats">
            <h2>Ваши параметры</h2>
            <ul>
                <li>Здоровье: <?php echo $player_health; ?></li>
                <li>Уровень: <?php echo $player_stats['level']; ?></li>
                <li>Сила: <?php echo $player_stats['strength']; ?></li>
                <li>Защита: <?php echo $player_stats['defense']; ?></li>
                <li>Ловкость: <?php echo $player_stats['agility']; ?></li>
            </ul>
        </div>

        <div class="enemy-stats">
            <h2>Параметры врага</h2>
            <ul>
                <li>Здоровье: <?php echo $enemy_health; ?>/<?php echo $enemy['max_health']; ?></li>
                <li>Уровень: <?php echo $enemy['level']; ?></li>
                <li>Сила: <?php echo $enemy['damage']; ?></li>
            </ul>
        </div>
    </div>

    <div class="combat-log">
        <h2>Лог боя</h2>
        <p><?php echo $combat_log; ?></p>
    </div>

    <div class="timer-container">
        <h2>Время на ход</h2>
        <div class="progress-bar">
            <div class="progress" id="progress"></div>
        </div>
    </div>

    <?php if ($battle_ended): ?>
        <p>
            <?php if ($player_health <= 0): ?>
                Вы проиграли бой! Вы перемещены на стартовые координаты (5, 5) с 0 здоровьем.
            <?php elseif ($enemy_health <= 0): ?>
                Вы победили врага!
            <?php endif; ?>
        </p>
        <form action="game.php" method="get">
            <button type="submit">Покинуть бой</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <button type="submit">Атаковать</button>
        </form>
    <?php endif; ?>

    <script>
        let timeLeft = 30;
        let progressElement = document.getElementById("progress");
        let timer = setInterval(updateTimer, 1000);

        function updateTimer() {
            if (timeLeft > 0) {
                timeLeft--;
                progressElement.style.width = (timeLeft / 30) * 100 + "%";
            } else {
                clearInterval(timer);
                playerTimeout();
            }
        }

        function playerTimeout() {
            fetch('battle.php?enemy_id=<?php echo $enemy_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'timeout=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "game.php";
                } else {
                    console.log('Ошибка обработки таймера');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
            });
        }
    </script>

</body>
</html>
