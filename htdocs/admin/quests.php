<?php
include 'db.php';

// Получаем список всех заданий
$stmt = $pdo->query("SELECT quests.*, cities.name AS city_name FROM quests 
                     JOIN cities ON quests.city_id = cities.id");
$quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление заданиями</title>
</head>
<body>
    <h1>Управление заданиями</h1>
    <a href="add_quest.php">Добавить новое задание</a>

    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Описание</th>
                <th>Город</th>
                <th>Опыт</th>
                <th>Деньги</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quests as $quest) { ?>
                <tr>
                    <td><?php echo $quest['id']; ?></td>
                    <td><?php echo $quest['title']; ?></td>
                    <td><?php echo $quest['description']; ?></td>
                    <td><?php echo $quest['city_name']; ?></td>
                    <td><?php echo $quest['experience_reward']; ?></td>
                    <td><?php echo $quest['money_reward']; ?></td>
                    <td>
                        <a href="edit_quest.php?id=<?php echo $quest['id']; ?>">Редактировать</a>
                        <a href="delete_quest.php?id=<?php echo $quest['id']; ?>">Удалить</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
