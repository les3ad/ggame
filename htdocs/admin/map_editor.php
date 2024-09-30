
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

// Обработка сохранения тайла через AJAX запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Добавляем отладку для вывода данных, которые пришли на сервер
    error_log(print_r($data, true)); // Логируем запрос для проверки данных

    if (isset($data['save_tile'])) {
        $x = $data['x'];
        $y = $data['y'];
        $tile_image = $data['tile_image'];
        $is_blocking = isset($data['is_blocking']) && $data['is_blocking'] == 1 ? 1 : 0;

        // Проверяем, существует ли тайл на этой координате
        $stmt = $pdo->prepare("SELECT * FROM tiles WHERE x = :x AND y = :y");
        $stmt->execute(['x' => $x, 'y' => $y]);
        $existingTile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingTile) {
            // Обновляем существующий тайл
            $stmt = $pdo->prepare("UPDATE tiles SET tile_image = :tile_image, is_blocking = :is_blocking WHERE x = :x AND y = :y");
            $stmt->execute(['tile_image' => $tile_image, 'is_blocking' => $is_blocking, 'x' => $x, 'y' => $y]);
            echo json_encode(['status' => 'updated', 'message' => "Тайл ($x, $y) обновлен как " . ($is_blocking ? 'непроходимый' : 'проходимый')]);
        } else {
            // Вставляем новый тайл
            $stmt = $pdo->prepare("INSERT INTO tiles (x, y, tile_image, is_blocking) VALUES (:x, :y, :tile_image, :is_blocking)");
            $stmt->execute(['x' => $x, 'y' => $y, 'tile_image' => $tile_image, 'is_blocking' => $is_blocking]);
            echo json_encode(['status' => 'added', 'message' => "Тайл ($x, $y) добавлен как " . ($is_blocking ? 'непроходимый' : 'проходимый')]);
        }
    }
}
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
    <form action="map_editor.php" method="POST" enctype="multipart/form-data">
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

    <div id="tile-coordinates" style="position: absolute; top: 10px; left: 10px; background-color: rgba(255,255,255,0.8); padding: 5px; border-radius: 5px; display: none;">Coordinates: (0, 0)</div>

    <!-- Поле для редактирования карты -->
    <div id="game-container"></div>

    <!-- Форма для обновления блокирующего тайла через интерфейс -->
    <h2>Mark Tile as Blocking</h2>
    <form action="map_editor.php" method="POST">
        <label for="x">X:</label>
        <input type="number" name="x" id="x" required>
        <label for="y">Y:</label>
        <input type="number" name="y" id="y" required>
        <label for="is_blocking">Blocking:</label>
        <input type="checkbox" name="is_blocking" id="is_blocking">
        <button type="submit" name="block_tile">Update Tile</button>
    </form>

    <div>
        <label>
            <input type="checkbox" id="blocking-checkbox">
            Blocking
        </label>
    </div>

    <script>
        const mapData = <?php echo json_encode($mapData); ?>;
    </script>
    <script src="../js/map_editor.js"></script>
</body>
</html>

