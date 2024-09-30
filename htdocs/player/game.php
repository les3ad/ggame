<?php
include '../lib/auth.php';
include_once '../lib/db.php';
include_once '../lib/player_stats.php';
include_once '../lib/enemies.php';
include_once '../lib/combat.php';
include_once '../lib/resources.php';
include_once '../lib/professions.php';
include_once '../lib/cities.php';  // Подключаем файл для работы с городами

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$player_stats = get_player_stats($user_id);

$player_x = $player_stats['x'];
$player_y = $player_stats['y'];
$player_health = $player_stats['health'];
$player_max_health = $player_stats['max_health'];
$player_level = $player_stats['level'];

// Рассчитываем 10% от максимального здоровья
$health_threshold = $player_max_health * 0.1;

// Проверяем, может ли игрок нападать (если его здоровье > 10%)
$can_attack = $player_health > $health_threshold;

// Получаем карту вокруг игрока
$stmt = $pdo->prepare("SELECT * FROM tiles WHERE x BETWEEN :minX AND :maxX AND y BETWEEN :minY AND :maxY");
$stmt->execute([
    'minX' => $player_x - 6,
    'maxX' => $player_x + 6,
    'minY' => $player_y - 6,
    'maxY' => $player_y + 6,
]);
$tiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Проверяем наличие города на текущих координатах
$city = get_city_at_position($player_x, $player_y);

// Получаем врагов на текущей клетке
$enemies = get_enemies_at_position($player_x, $player_y);

// Получаем ресурсы на текущей позиции игрока с учётом уровня профессии
$resources = get_resources_at_position($player_x, $player_y, $user_id);

// Условие для отображения кнопки врагов
$enemies_exist = !empty($enemies);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Игра</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Подключение jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Подключение библиотеки PIXI.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pixi.js/7.0.0/pixi.min.js"></script>
    <!-- Подключение файла с картой -->
    <script src="../lib/map.js"></script>
    <link rel="stylesheet" href="../css/mobile_styles.css">
    <link rel="stylesheet" href="../css/menu_styles.css">
    <link rel="stylesheet" href="../css/game_styles.css">
</head>
<body>
    <div class="player-coords">
        X = <span id="playerX"><?php echo $player_x; ?></span>, Y = <span id="playerY"><?php echo $player_y; ?></span>
    </div>

    <div class="player-stats">
        Здоровье: <strong id="playerHealth"><?php echo round($player_health); ?></strong>/<strong><?php echo round($player_max_health); ?></strong>
    </div>
<!-- Индикатор состояния боя -->
<div id="battleIndicator" style="display: none; background-color: red; color: white; padding: 10px; position: absolute; top: 10px; left: 10px;">
        Вы находитесь в бою!
    </div>

    <div id="game-container"></div>

    <script>
        // Ваша логика карты и игры
    </script>
    <!-- Если есть враги, отображаем кнопку врагов -->
    <?php if ($enemies_exist) { ?>
        <div class="enemy-info" onclick="toggleEnemyList()">
            <h4>Враги</h4>
        </div>
        <div class="enemies-list" id="enemiesList" style="display: none;">
            <?php foreach ($enemies as $enemy) { ?>
                <div class="enemy-card">
                    <div class="enemy-details">
                        <span><?php echo htmlspecialchars($enemy['name']); ?> (уровень: <?php echo $enemy['level']; ?>)</span>
                        <span>HP: <?php echo $enemy['health']; ?>/<?php echo $enemy['max_health']; ?></span>
                    </div>
                    <div class="enemy-actions">
                        <?php if ($can_attack) { ?>
                            <button onclick="attackEnemy(<?php echo $enemy['id']; ?>)">Атаковать</button>
                        <?php } else { ?>
                            <span class="weak-message">Вы слишком слабы для атаки</span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Если есть ресурсы, отображаем кнопку ресурсов -->
    <?php if (!empty($resources)) { ?>
        <div class="resource-info" onclick="toggleResourceList()">
            <h4>Ресурсы</h4>
        </div>
        <div class="resources-list" id="resourcesList" style="display: none;">
            <?php foreach ($resources as $resource) { ?>
                <div class="resource-card">
                    <div class="resource-details">
                        <span><?php echo htmlspecialchars($resource['name']); ?></span>
                    </div>
                    <div class="resource-actions">
                        <?php if ($resource['can_collect']) { ?>
                            <button onclick="collectResource(<?php echo $resource['id']; ?>)">Собрать</button>
                        <?php } else { ?>
                            <span>Недостаточный уровень профессии</span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Если игрок находится в городе, отображаем кнопку города -->
    <?php if ($city) { ?>
        <div class="city-info" onclick="enterCity()">
            <h4><?php echo htmlspecialchars($city['name']); ?></h4>
        </div>
    <?php } ?>

    <div id="game-container"></div>

    <!-- Меню виджет -->
    <div class="menu-widget">
        <button class="menu-toggle" id="menu-toggle-btn">Меню</button>
        <div class="menu-content" id="menuContent">
            <ul>
                <?php include '../menu/game_menu.php'; ?>
            </ul>
        </div>
    </div>

    <!-- Модальное окно для сообщения о низком уровне здоровья -->
    <div id="lowHealthModal" class="modal" style="display: none;">
        <div class="modal-content">
            <p>Вы слишком слабы для передвижения. Подождите, пока ваше здоровье будет больше 10%.</p>
            <button onclick="closeModal()">Закрыть</button>
        </div>
    </div>

    <script>
        // Инициализация карты
        initMap(<?php echo $player_x; ?>, <?php echo $player_y; ?>, <?php echo json_encode($tiles); ?>, <?php echo $player_health; ?>, <?php echo $player_max_health; ?>, <?php echo json_encode($resources); ?>);

        function attackEnemy(enemy_spawn_point_id) {
            if (!enemy_spawn_point_id) {
                console.error("ID врага не передан.");
                return;
            }
            // Перенаправляем на страницу боя
            window.location.href = 'battle.php?enemy_id=' + enemy_spawn_point_id;
        }

        function toggleEnemyList() {
            const enemyList = document.getElementById('enemiesList');
            enemyList.style.display = (enemyList.style.display === 'none' || enemyList.style.display === '') ? 'block' : 'none';
        }

        function toggleResourceList() {
            const resourceList = document.getElementById('resourcesList');
            resourceList.style.display = (resourceList.style.display === 'none' || resourceList.style.display === '') ? 'block' : 'none';
        }

        function enterCity() {
            window.location.href = 'cities.php';
        }

        // Обработчик для кнопки меню
        $(document).ready(function() {
            $('#menu-toggle-btn').on('click', function() {
                const menuContent = $('#menuContent');
                // Проверяем класс open для переключения видимости меню
                menuContent.toggleClass('open');
            });
        });

        // Обновление здоровья игрока через AJAX
        function updatePlayerHealth() {
            $.ajax({
                url: '../player/get_health.php',  // Убедитесь, что путь правильный
                method: 'GET',
                dataType: 'json',
                
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Не удалось получить обновленные данные здоровья:', textStatus, errorThrown);
                }
            });
        }

        // Запускаем обновление здоровья каждые 5 секунд
        setInterval(updatePlayerHealth, 5000);

        // Функции для модального окна
        function showModal() {
            document.getElementById('lowHealthModal').style.display = 'flex'; // Показываем модальное окно
        }

        function closeModal() {
            document.getElementById('lowHealthModal').style.display = 'none'; // Закрываем модальное окно
        }
    </script>
</body>
</html>
