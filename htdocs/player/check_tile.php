<?php
include '../lib/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$x = $data['x'];
$y = $data['y'];

// Проверяем, является ли клетка блокированной
$stmt = $pdo->prepare("SELECT is_blocking FROM tiles WHERE x = :x AND y = :y");
$stmt->execute(['x' => $x, 'y' => $y]);
$tile = $stmt->fetch();

if ($tile && $tile['is_blocking']) {
    echo json_encode(['can_move' => false]);
} else {
    echo json_encode(['can_move' => true]);
}
?>
