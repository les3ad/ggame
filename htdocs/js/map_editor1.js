const app = new PIXI.Application({ width: 800, height: 800 });
document.getElementById('game-container').appendChild(app.view);

const tileSize = 16;
const mapSize = 50;
const mapContainer = new PIXI.Container();
app.stage.addChild(mapContainer);

const tileTextures = {}; // Для хранения текстур тайлов

// Загружаем существующие тайлы из базы данных
mapData.forEach(tile => {
    const texture = PIXI.Texture.from('../uploads/tiles/' + tile.tile_image);
    tileTextures[`${tile.x},${tile.y}`] = texture;
});

// Функция для отрисовки карты
function drawMap() {
    mapContainer.removeChildren(); // Очищаем карту перед отрисовкой
    for (let x = 0; x < mapSize; x++) {
        for (let y = 0; y < mapSize; y++) {
            const tileKey = `${x},${y}`;
            if (tileTextures[tileKey]) {
                const sprite = new PIXI.Sprite(tileTextures[tileKey]);
                sprite.x = x * tileSize;
                sprite.y = y * tileSize;
                sprite.width = tileSize;
                sprite.height = tileSize;
                sprite.interactive = true;
                sprite.on('click', () => removeTile(x, y)); // Удаление при клике
                mapContainer.addChild(sprite);
            } else {
                // Пустые клетки
                const emptyTile = new PIXI.Graphics();
                emptyTile.lineStyle(1, 0xcccccc, 1);
                emptyTile.drawRect(x * tileSize, y * tileSize, tileSize, tileSize);
                emptyTile.interactive = true;
                emptyTile.on('click', () => addTile(x, y)); // Добавление тайла при клике
                mapContainer.addChild(emptyTile);
            }
        }
    }
}

drawMap(); // Изначальная отрисовка карты

let selectedTexture = null;

// Выбор тайла из загруженных
document.querySelectorAll('.tile').forEach(tile => {
    tile.addEventListener('click', function () {
        selectedTexture = PIXI.Texture.from(this.src);
    });
});

// Добавление тайла на карту
function addTile(x, y) {
    if (!selectedTexture) return;

    const sprite = new PIXI.Sprite(selectedTexture);
    sprite.x = x * tileSize;
    sprite.y = y * tileSize;
    sprite.width = tileSize;
    sprite.height = tileSize;
    sprite.interactive = true;
    sprite.on('click', () => removeTile(x, y)); // Удаление при клике
    mapContainer.addChild(sprite);

    tileTextures[`${x},${y}`] = selectedTexture;

    // Сохранение тайла в базу данных
    saveTile(x, y, selectedTexture.textureCacheIds[0]);
}

// Удаление тайла
function removeTile(x, y) {
    delete tileTextures[`${x},${y}`];
    drawMap(); // Перерисовываем карту, чтобы отобразить удаление тайла

    // Удаляем тайл из базы данных
    deleteTile(x, y);
}

// Сохранение тайла в базу данных
function saveTile(x, y, tileImage) {
    fetch('save_tile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ x, y, tileImage }),
    });
}

// Удаление тайла из базы данных
function deleteTile(x, y) {
    fetch('delete_tile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ x, y }),
    });
}
