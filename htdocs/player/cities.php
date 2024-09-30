<?php
include '../lib/auth.php';
include '../lib/db.php';
include '../lib/cities.php'; // Здесь должна быть функция get_city_at_position()
include '../lib/player_stats.php'; // Функция get_player_stats()

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$player_stats = get_player_stats($user_id);

// Определяем город, в котором находится игрок
$city = get_city_at_position($player_stats['x'], $player_stats['y']);

if (!$city) {
    header('Location: ../game.php');  // Если игрок не в городе, перенаправляем его обратно в игру
    exit();
}

// Получаем пункты меню города
$city_menu_items = get_city_menu_items($city['id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $city['name']; ?></title>
    <link rel="stylesheet" href="../css/city_styles.css">
</head>
<body>

<div class="city-container">
    <h2><?php echo $city['name']; ?></h2>

    <ul>
        <?php foreach ($city_menu_items as $item) { ?>
            <li><a href="<?php echo $item['menu_action']; ?>"><?php echo $item['menu_name']; ?></a></li>
        <?php } ?>
    </ul>

    <button onclick="leaveCity()">Покинуть город</button>
</div>

<script>
    function leaveCity() {
        fetch('leave_city.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = 'game.php';
            } else {
                alert('Не удалось покинуть город.');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
        });
    }
</script>

</body>
</html>
