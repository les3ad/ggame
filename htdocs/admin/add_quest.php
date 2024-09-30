<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $city_id = $_POST['city_id'];
    $experience_reward = $_POST['experience_reward'];
    $money_reward = $_POST['money_reward'];
    $stat_point_reward = $_POST['stat_point_reward'];

    // Вставляем задание
    $stmt = $pdo->prepare("INSERT INTO quests (title, description, city_id, experience_reward, money_reward, stat_point_reward) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $city_id, $experience_reward, $money_reward, $stat_point_reward]);

    $quest_id = $pdo->lastInsertId();  // Получаем ID задания

    // Добавляем условия задания — предметы
    foreach ($_POST['items'] as $item_id => $quantity) {
        if ($quantity > 0) {  // Проверяем, что количество больше 0
            $stmt = $pdo->prepare("INSERT INTO quest_items (quest_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$quest_id, $item_id, $quantity]);
        }
    }

    // Добавляем условия задания — враги
    foreach ($_POST['enemies'] as $enemy_id => $quantity) {
        if ($quantity > 0) {  // Проверяем, что количество больше 0
            $stmt = $pdo->prepare("INSERT INTO quest_enemies (quest_id, enemy_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$quest_id, $enemy_id, $quantity]);
        }
    }

    // Добавляем награды задания — предметы
    foreach ($_POST['rewards'] as $item_id => $quantity) {
        if ($quantity > 0) {  // Проверяем, что количество больше 0
            $stmt = $pdo->prepare("INSERT INTO quest_rewards (quest_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$quest_id, $item_id, $quantity]);
        }
    }

    header('Location: quests.php');
    exit();
}

// Получаем список городов, предметов и врагов
$cities = $pdo->query("SELECT * FROM cities")->fetchAll(PDO::FETCH_ASSOC);
$items = $pdo->query("SELECT * FROM resources")->fetchAll(PDO::FETCH_ASSOC);
$enemies = $pdo->query("SELECT * FROM enemies")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить задание</title>
</head>
<body>
    <h1>Добавить новое задание</h1>

    <form method="POST">
        <label>Название:</label>
        <input type="text" name="title" required><br>

        <label>Описание:</label>
        <textarea name="description" required></textarea><br>

        <label>Город:</label>
        <select name="city_id" required>
            <?php foreach ($cities as $city) { ?>
                <option value="<?php echo $city['id']; ?>"><?php echo $city['name']; ?></option>
            <?php } ?>
        </select><br>

        <label>Награда: Опыт</label>
        <input type="number" name="experience_reward" value="0"><br>

        <label>Награда: Деньги</label>
        <input type="number" name="money_reward" value="0"><br>

        <label>Награда: Очки характеристик</label>
        <input type="number" name="stat_point_reward" value="0"><br>

        <h3>Условия выполнения задания</h3>
        <label>Предметы (укажите количество):</label><br>
        <?php foreach ($items as $item) { ?>
            <label><?php echo $item['name']; ?></label>
            <input type="number" name="items[<?php echo $item['id']; ?>]" min="0" value="0"><br>
        <?php } ?>

        <label>Враги (укажите количество):</label><br>
        <?php foreach ($enemies as $enemy) { ?>
            <label><?php echo $enemy['name']; ?></label>
            <input type="number" name="enemies[<?php echo $enemy['id']; ?>]" min="0" value="0"><br>
        <?php } ?>

        <h3>Награды</h3>
        <label>Предметы (укажите количество):</label><br>
        <?php foreach ($items as $item) { ?>
            <label><?php echo $item['name']; ?></label>
            <input type="number" name="rewards[<?php echo $item['id']; ?>]" min="0" value="0"><br>
        <?php } ?>

        <button type="submit">Создать задание</button>
    </form>

    <a href="quests.php">Назад к списку</a>
</body>
</html>
