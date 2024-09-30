<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Файл: cron/regenerate_health.php
include '../lib/db.php'; // Подключаем базу данных

// Получаем игроков, которые не в бою и здоровье которых меньше максимального
$stmt = $pdo->query("
    SELECT id, health, max_health, defense, agility, last_health_update
    FROM users
    WHERE in_battle = 0 AND health < max_health
");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($players as $player) {
    $current_time = time();
    $last_update_time = strtotime($player['last_health_update']);
    $time_difference = $current_time - $last_update_time; // В секундах

    if ($time_difference <= 0) {
        continue; // Если время не прошло, переходим к следующему игроку
    }

    // Расчет общей скорости регенерации
    $base_regen_rate = 0.01; // 1% в минуту
    $defense_bonus = ($player['defense'] / 10) * 0.005;
    $agility_bonus = ($player['agility'] / 10) * 0.005;
    $total_regen_rate_per_minute = $base_regen_rate + $defense_bonus + $agility_bonus;

    // Ограничиваем скорость регенерации
    $min_regen_rate_per_minute = 0.01; // 1% в минуту
    $max_regen_rate_per_minute = 0.05; // 5% в минуту
    $total_regen_rate_per_minute = max($min_regen_rate_per_minute, min($total_regen_rate_per_minute, $max_regen_rate_per_minute));

    $total_regen_rate_per_second = $total_regen_rate_per_minute / 60;

    // Расчет количества восстановленного здоровья
    $regen_amount = $player['max_health'] * $total_regen_rate_per_second * $time_difference;
    $new_health = min($player['max_health'], $player['health'] + $regen_amount);

    // Обновляем здоровье и время последнего обновления, если здоровье изменилось
    if ($new_health > $player['health']) {
        $stmt_update = $pdo->prepare("
            UPDATE users
            SET health = :health, last_health_update = NOW()
            WHERE id = :id
        ");
        $stmt_update->execute(['health' => $new_health, 'id' => $player['id']]);
    } else {
        // Обновляем только время последнего обновления
        $stmt_update_time = $pdo->prepare("
            UPDATE users
            SET last_health_update = NOW()
            WHERE id = :id
        ");
        $stmt_update_time->execute(['id' => $player['id']]);
    }
}
?>
