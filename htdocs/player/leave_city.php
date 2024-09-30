<?php
include '../lib/auth.php';
include '../lib/db.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Вы не авторизованы']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Здесь убираем логику изменения координат игрока при выходе из города

// Возвращаем успешный ответ
echo json_encode(['success' => true, 'message' => 'Вы покинули город.']);
exit();
