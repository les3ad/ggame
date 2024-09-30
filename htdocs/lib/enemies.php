<?php
include_once 'db.php';

// Получение врагов на заданной позиции
if (!function_exists('get_enemies_at_position')) {
    function get_enemies_at_position($x, $y) {
        global $pdo;
        
        // Получаем врагов, которые могут быть на данной позиции
        $stmt = $pdo->prepare("
            SELECT esp.*, e.name, e.damage, e.level, e.strength, e.defense, e.agility, e.dodge, e.luck, e.exp_reward
            FROM enemy_spawn_points esp
            JOIN enemies e ON esp.enemy_id = e.id
            WHERE esp.x = :x AND esp.y = :y
            AND (
                esp.is_dead = 0 OR 
                (esp.is_dead = 1 AND TIMESTAMPDIFF(SECOND, esp.death_time, NOW()) >= esp.respawn_time)
            )
        ");
        $stmt->execute(['x' => $x, 'y' => $y]);
        $enemies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Проверяем, если враг был мертв, но его время респавна истекло — оживляем
        foreach ($enemies as &$enemy) {
            if ($enemy['is_dead'] == 1 && (time() - strtotime($enemy['death_time']) >= $enemy['respawn_time'])) {
                // Оживляем врага
                $stmt_update = $pdo->prepare("UPDATE enemy_spawn_points SET is_dead = 0, death_time = NULL, health = max_health WHERE id = :id");
                $stmt_update->execute(['id' => $enemy['id']]);
                $enemy['health'] = $enemy['max_health'];
                $enemy['is_dead'] = 0;
                $enemy['death_time'] = null;
            }
        }

        return $enemies;
    }
}

// Обновление здоровья врага
if (!function_exists('update_enemy_health')) {
    function update_enemy_health($enemy_spawn_point_id, $new_health) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE enemy_spawn_points SET health = :health WHERE id = :id");
        $stmt->execute(['health' => $new_health, 'id' => $enemy_spawn_point_id]);
    }
}

// Отметить врага как мертвого
if (!function_exists('mark_enemy_dead')) {
    function mark_enemy_dead($enemy_spawn_point_id) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE enemy_spawn_points SET is_dead = 1, death_time = NOW() WHERE id = :id");
        $stmt->execute(['id' => $enemy_spawn_point_id]);
    }
}

// Получение врага по ID точки спавна
if (!function_exists('get_enemy_by_spawn_id')) {
    function get_enemy_by_spawn_id($enemy_spawn_point_id) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT esp.*, e.name, e.damage, e.level, e.strength, e.defense, e.agility, e.dodge, e.luck, e.exp_reward
            FROM enemy_spawn_points esp
            JOIN enemies e ON esp.enemy_id = e.id
            WHERE esp.id = :id
        ");
        $stmt->execute(['id' => $enemy_spawn_point_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
