<?php
include 'db.php';

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $city_id = $_POST['city_id'];
    $experience_reward = $_POST['experience_reward'];
    $money_reward = $_POST['money_reward'];
    $stat_point_reward = $_POST['stat_point_reward'];

    // Обновляем задание
    $stmt = $pdo->prepare("UPDATE quests SET title = ?, description = ?, city_id = ?, experience_reward = ?, money_reward = ?, stat_point_reward = ? 
                           WHERE id = ?");
    $stmt->execute([$title, $description, $city_id, $experience_reward, $money_reward, $stat_point_reward, $id]);

    // Обновляем условия и награды (удаляем старые и добавляем новые)
    $pdo->prepare("DELETE FROM quest_items WHERE quest_id = ?")->execute([$id]);
    foreach ($_POST['items'] as $item_id => $quantity) {
        if ($quantity > 0) {
            $stmt = $pdo->prepare("INSERT INTO quest_items (quest_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$id, $item_id, $quantity]);
        }
    }

    $pdo->prepare("DELETE FROM quest_enemies WHERE quest_id = ?")->execute([$id]);
    foreach ($_POST['enemies'] as $enemy_id => $quantity) {
        if ($quantity > 0) {
            $stmt = $pdo->prepare("INSERT INTO quest_enemies (quest_id, enemy_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$id, $enemy_id, $quantity]);
        }
    }

    $pdo->prepare("DELETE FROM quest_rewards WHERE quest_id = ?")->execute([$id]);
    foreach ($_POST['rewards'] as $item_id => $quantity) {
        if ($quantity > 0) {
            $stmt = $pdo->prepare("INSERT INTO quest_rewards (quest_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$id, $item_id, $quantity]);
        }
    }

    header('Location: quests.php');
    exit();
}

// Получаем данные задания
$stmt = $pdo->prepare("SELECT * FROM quests WHERE id = ?");
$stmt->execute([$id]);
$quest = $stmt->fetch(PDO::FETCH_ASSOC);

// Получаем данные о предметах, врагах и городах
$cities = $pdo->query("SELECT * FROM cities")->fetchAll(PDO::FETCH_ASSOC);
$items = $pdo->query("SELECT * FROM resources")->fetchAll(PDO::FETCH_ASSOC);
$enemies = $pdo->query("SELECT * FROM enemies")->fetchAll(PDO::FETCH_ASSOC);

// Получаем текущие условия задания (предметы и враги)
$current_items = $pdo->prepare("SELECT item_id, quantity FROM quest_items WHERE quest_id = ?");
$current_items->execute([$id]);
$current_items = $current_items->fetchAll(PDO::FETCH_ASSOC);

$current_enemies = $pdo->prepare("SELECT enemy_id, quantity FROM quest_enemies WHERE quest_id = ?");
$current_enemies->execute([$id]);
$current_enemies = $current_enemies->fetchAll(PDO::FETCH_ASSOC);

// Получаем текущие награды задания
$current_rewards = $pdo->prepare("SELECT item_id, quantity FROM quest_rewards WHERE quest_id = ?");
$current_rewards->execute([$id]);
$current_rewards = $current_rewards->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать задание</title>
</head>
<body>
    <h1>Редактировать задание</h1>

    <form method="POST">
        <label>Название:</label>
        <input type="text" name="title" value="<?php echo $quest['title']; ?>" required><br>

        <label>Описание:</label>
        <textarea name="description" required><?php echo $quest['description']; ?></textarea><br>

        <label>Город:</label>
        <select name="city_id" required>
            <?php foreach ($cities as $city) { ?>
                <option value="<?php echo $city['id']; ?>" <?php if ($quest['city_id'] == $city['id']) echo 'selected'; ?>>
                    <?php echo $city['name']; ?>
                </option>
            <?php } ?>
        </select><br>

        <label>Награда: Опыт</label>
        <input type="number" name="experience_reward" value="<?php echo $quest['experience_reward']; ?>"><br>

        <label>Награда: Деньги</label>
        <input type="number" name="money_reward" value="<?php echo $quest['money_reward']; ?>"><br>

        <label>Награда: Очки характеристик</label>
        <input type="number" name="stat_point_reward" value="<?php echo $quest['stat_point_reward']; ?>"><br>

        <h3>Условия выполнения задания</h3>
        <label>Предметы (укажите количество):</label><br>
        <?php foreach ($items as $item) {
            $quantity = 0;
            foreach ($current_items as $current_item) {
                if ($current_item['item_id'] == $item['id']) {
                    $quantity = $current_item['quantity'];
                    break;
                }
            } ?>
            <label><?php echo $item['name']; ?></label>
            <input type="number" name="items[<?php echo $item['id']; ?>]" min="0" value="<?php echo $quantity; ?>"><br>
        <?php } ?>

        <label>Враги (укажите количество):</label><br>
        <?php foreach ($enemies as $enemy) {
            $quantity = 0;
            foreach ($current_enemies as $current_enemy) {
                if ($current_enemy['enemy_id'] == $enemy['id']) {
                    $quantity = $current_enemy['quantity'];
                    break;
                }
            } ?>
            <label><?php echo $enemy['name']; ?></label>
            <input type="number" name="enemies[<?php echo $enemy['id']; ?>]" min="0" value="<?php echo $quantity; ?>"><br>
        <?php } ?>

        <h3>Награды</h3>
        <label>Предметы (укажите количество):</label><br>
        <?php foreach ($items as $item) {
            $quantity = 0;
            foreach ($current_rewards as $current_reward) {
                if ($current_reward['item_id'] == $item['id']) {
                    $quantity = $current_reward['quantity'];
                    break;
                }
            } ?>
            <label><?php echo $item['name']; ?></label>
            <input type="number" name="rewards[<?php echo $item['id']; ?>]" min="0" value="<?php echo $quantity; ?>"><br>
        <?php } ?>

        <button type="submit">Обновить задание</button>
    </form>

    <a href="quests.php">Назад к списку</a>
</body>
</html>
