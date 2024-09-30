<?php
include '../lib/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$x = $data['x'];
$y = $data['y'];

$stmt = $pdo->prepare("DELETE FROM tiles WHERE x = :x AND y = :y");
$stmt->execute(['x' => $x, 'y' => $y]);
?>
