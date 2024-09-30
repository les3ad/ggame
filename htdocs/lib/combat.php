<?php
include 'db.php';
include 'enemies.php';
include_once 'player_stats.php';
include_once 'quests.php';  // Подключим для работы с квестами

// Атака врага с учетом всех характеристик
function attack_enemy($user_id, $enemy_spawn_point_id) {
    global $pdo;

    // Получаем данные игрока
    $stmt = $pdo->prepare("SELECT health, strength, defense, agility, dodge, luck, level, exp, in_battle FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $player = $stmt->fetch();

    // Получаем данные врага
    $enemy = get_enemy_by_spawn_id($enemy_spawn_point_id);

    // Проверка на существование врага
    if (!$enemy) {
        return [
            'log' => "Враг не найден.",
            'enemy_health' => 0,
            'player_health' => $player['health']
        ];
    }

    // Проверка, жив ли враг
    if ($enemy['is_dead']) {
        return [
            'log' => "Враг уже мертв.",
            'enemy_health' => 0,
            'player_health' => $player['health']
        ];
    }

    $combat_log = "";

    // Проверка на уворот врага
    $enemy_dodge_chance = rand(0, 100);
    if ($enemy_dodge_chance < $enemy['dodge']) {
        $combat_log = "Враг уклонился от вашей атаки!";
        return [
            'log' => $combat_log,
            'enemy_health' => $enemy['health'],
            'player_health' => $player['health']
        ];
    }

    // Рассчитываем урон от игрока
    $base_damage = rand($player['strength'] * 0.8, $player['strength'] * 1.2);

    // Шанс критического удара (учитываем ловкость и удачу)
    $crit_chance = rand(0, 100);
    $crit_multiplier = ($crit_chance < ($player['agility'] + $player['luck'])) ? 1.5 : 1; // 50% увеличенный урон при крите

    // Влияние защиты врага на урон
    $damage_dealt = max(1, ($base_damage * $crit_multiplier) - ($enemy['defense'] * 0.7)); // Вводим множитель для смягчения влияния защиты

    // Обновляем здоровье врага
    $enemy_health_after_attack = $enemy['health'] - $damage_dealt;

    if ($enemy_health_after_attack <= 0) {
        $enemy_health_after_attack = 0;
        mark_enemy_dead($enemy_spawn_point_id); // Функция для пометки врага как мертвого
        $combat_log .= " Вы убили врага и нанесли {$damage_dealt} урона.";

        // Проверяем наличие активных квестов на этого врага
        $active_quests = get_active_quests_for_player($user_id);
        foreach ($active_quests as $quest) {
            $quest_enemies = get_quest_enemies($quest['quest_id']);
            foreach ($quest_enemies as $quest_enemy) {
                if ($quest_enemy['enemy_id'] == $enemy['enemy_id']) {
                    // Обновляем количество убитых врагов в таблице player_killed_enemies
                    $stmt = $pdo->prepare("
                        INSERT INTO player_killed_enemies (player_id, quest_id, enemy_id, killed)
                        VALUES (?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE killed = killed + 1
                    ");
                    $stmt->execute([$user_id, $quest['quest_id'], $enemy['enemy_id']]);
                    $combat_log .= " Убито врагов для квеста: {$quest_enemy['name']}.";
                }
            }
        }
    } else {
        update_enemy_health($enemy_spawn_point_id, $enemy_health_after_attack);
        $combat_log .= " Вы нанесли врагу {$damage_dealt} урона.";
    }

    // Если враг выжил, он атакует игрока
    if ($enemy_health_after_attack > 0) {
        $enemy_base_damage = rand($enemy['strength'] * 0.8, $enemy['strength'] * 1.2);
        $enemy_damage_dealt = max(1, $enemy_base_damage - ($player['defense'] * 0.7));

        $player_dodge_chance = rand(0, 100);
        if ($player_dodge_chance < $player['dodge']) {
            $combat_log .= " Вы уклонились от атаки врага!";
        } else {
            $player_health_after_attack = $player['health'] - $enemy_damage_dealt;

            if ($player_health_after_attack <= 0) {
                $player_health_after_attack = 0;
                mark_player_dead($user_id);  // Функция для пометки игрока как мертвого
                $combat_log .= " Враг нанес вам {$enemy_damage_dealt} урона. Вы погибли!";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET health = :health WHERE id = :id");
                $stmt->execute(['health' => $player_health_after_attack, 'id' => $user_id]);
                $combat_log .= " Враг нанес вам {$enemy_damage_dealt} урона.";
            }
        }
    }

    // Если враг был убит, игрок получает опыт
    if ($enemy_health_after_attack <= 0) {
        $xp_gain = calculate_experience($player['level'], $enemy['level'], $enemy['exp_reward']);
        $stmt = $pdo->prepare("UPDATE users SET exp = exp + :xp, in_battle = 0 WHERE id = :id");
        $stmt->execute(['xp' => $xp_gain, 'id' => $user_id]);

        $combat_log .= " Вы победили врага и получили {$xp_gain} опыта!";
    }

    return [
        'log' => $combat_log,
        'enemy_health' => $enemy_health_after_attack,
        'player_health' => $player_health_after_attack ?? $player['health']
    ];
}

// Функция расчета опыта
function calculate_experience($player_level, $enemy_level, $base_exp) {
    $level_difference = $enemy_level - $player_level;
    $xp_gain = $base_exp * pow(1.1, $level_difference);
    return max(1, round($xp_gain));
}

// Функция для отметки игрока как мертвого
function mark_player_dead($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET health = 0, death_time = NOW(), in_battle = 0 WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
}
?>