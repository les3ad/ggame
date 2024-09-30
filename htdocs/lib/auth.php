<?php
include 'db.php';

session_start();
// Функция для проверки авторизации пользователя
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
// Регистрация пользователя
function register($username, $password) {
    global $pdo;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, level, x, y) VALUES (:username, :password, 1, 5, 5)");
    return $stmt->execute(['username' => $username, 'password' => $hashedPassword]);
}

// Авторизация пользователя
function login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['player_level'] = $user['level'];
        $_SESSION['player_x'] = $user['x'];
        $_SESSION['player_y'] = $user['y'];
        return true;
    }
    return false;
}

// Обновление позиции и уровня игрока
function update_player_position($userId, $x, $y) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET x = :x, y = :y WHERE id = :id");
    $stmt->execute(['x' => $x, 'y' => $y, 'id' => $userId]);
}
