<?php
include '../lib/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$x = $data['x'];
$y = $data['y'];
$tileImage = basename($data['tileImage']);

$stmt = $pdo->prepare("REPLACE INTO tiles (x, y, tile_image) VALUES (:x, :y, :tile_image)");
$stmt->execute(['x' => $x, 'y' => $y, 'tile_image' => $tileImage]);
?>
