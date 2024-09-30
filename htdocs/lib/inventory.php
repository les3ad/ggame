<?php
// Подключение к базе данных
include 'db.php';

function get_player_inventory($user_id) {
    global $pdo;

    // Группируем ресурсы по их типу и считаем количество каждого
    $stmt = $pdo->prepare("
        SELECT r.name, pi.resource_id, pi.quantity
        FROM player_inventory pi
        JOIN resources r ON pi.resource_id = r.id
        WHERE pi.player_id = :player_id
    ");
    $stmt->execute(['player_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Функция для выброса ресурса из инвентаря
function drop_resource($user_id, $resource_id, $quantity) {
    global $pdo;

    // Проверяем, есть ли у игрока достаточно ресурсов
    $stmt = $pdo->prepare("
        SELECT quantity 
        FROM player_inventory 
        WHERE player_id = :user_id AND resource_id = :resource_id
    ");
    $stmt->execute(['user_id' => $user_id, 'resource_id' => $resource_id]);
    $current_quantity = $stmt->fetchColumn();

    if ($current_quantity >= $quantity) {
        // Удаляем указанное количество ресурса
        $stmt = $pdo->prepare("
            UPDATE player_inventory 
            SET quantity = quantity - :quantity 
            WHERE player_id = :user_id AND resource_id = :resource_id
        ");
        $stmt->execute([
            'quantity' => $quantity,
            'user_id' => $user_id,
            'resource_id' => $resource_id
        ]);

        // Если количество ресурса стало 0, удаляем запись из инвентаря
        $stmt = $pdo->prepare("
            DELETE FROM player_inventory 
            WHERE player_id = :user_id AND resource_id = :resource_id AND quantity <= 0
        ");
        $stmt->execute(['user_id' => $user_id, 'resource_id' => $resource_id]);
    }
}
?>
