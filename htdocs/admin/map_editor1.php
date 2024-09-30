<?php
include '../lib/db.php';

// Обработка загрузки изображений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['tile_image'])) {
    $targetDir = "../uploads/tiles/";
    $targetFile = $targetDir . basename($_FILES["tile_image"]["name"]);

    // Проверка типа файла
    $check = getimagesize($_FILES["tile_image"]["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($_FILES["tile_image"]["tmp_name"], $targetFile)) {
            // Сохранение имени файла в базе данных
            $stmt = $pdo->prepare("INSERT INTO uploads (image_name) VALUES (:image_name)");
            $stmt->execute(['image_name' => basename($_FILES["tile_image"]["name"])]);

            echo "Файл " . htmlspecialchars(basename($_FILES["tile_image"]["name"])) . " был загружен.";
        } else {
            echo "Ошибка при загрузке файла.";
        }
    } else {
        echo "Файл не является изображением.";
    }
}

// Получение загруженных тайлов из базы данных
$stmt = $pdo->query("SELECT * FROM uploads");
$uploadedTiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение текущей карты из базы данных
$stmt = $pdo->query("SELECT * FROM tiles");
$mapData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Map Editor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pixi.js/7.0.0/pixi.min.js"></script>
</head>
<body>
    <h1>Map Editor</h1>
    
    <!-- Форма для загрузки новых тайлов -->
    <form action="map_editor1.php" method="POST" enctype="multipart/form-data">
        <label for="tile_image">Upload Tile:</label>
        <input type="file" name="tile_image" id="tile_image">
        <button type="submit">Upload</button>
    </form>

    <h2>Available Tiles</h2>
    <div class="tile-list">
        <?php if (empty($uploadedTiles)): ?>
            <p>No tiles available.</p>
        <?php else: ?>
            <?php foreach ($uploadedTiles as $tile): ?>
                <img src="../uploads/tiles/<?php echo htmlspecialchars($tile['image_name']); ?>" class="tile" style="width: 32px; height: 32px;">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Поле для редактирования карты -->
    <div id="game-container"></div>

    <script>
        const mapData = <?php echo json_encode($mapData); ?>;
    </script>
    <script src="../js/map_editor1.js"></script>
</body>
</html>
