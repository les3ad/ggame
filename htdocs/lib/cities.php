<?php
include 'db.php';

// Получение города по координатам
function get_city_at_position($x, $y) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM city_spawn_points sp
        JOIN cities c ON sp.city_id = c.id
        WHERE sp.x = :x AND sp.y = :y
    ");
    $stmt->execute(['x' => $x, 'y' => $y]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Функция для получения меню города
function get_city_menu_items($city_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM city_menu_items
        WHERE city_id = :city_id
    ");
    $stmt->execute(['city_id' => $city_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Возвращаем список пунктов меню города
}
?>
