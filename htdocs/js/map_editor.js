
const app = new PIXI.Application({ width: window.innerWidth, height: window.innerHeight });
document.getElementById('game-container').appendChild(app.view);

const tileSize = 30; // Размер тайла
const mapSize = 200; // Размер карты (200x200 клеток)
const mapContainer = new PIXI.Container(); // Контейнер для карты
app.stage.addChild(mapContainer);

const tileTextures = {}; // Для хранения текстур тайлов

// Элемент для отображения координат
const coordinatesDisplay = document.getElementById('tile-coordinates');

// Флажок для блокировки
const blockingCheckbox = document.getElementById('blocking-checkbox');

// Переменная для хранения выбранной текстуры
let selectedTexture = null;
let isDrawing = false; // Флаг для отслеживания рисования
let isSelecting = false; // Флаг для выделения области
let startX, startY, endX, endY; // Начальные и конечные координаты выделения

// Функция для загрузки текстур тайлов и их отображения на карте
mapData.forEach(tile => {
    const texture = PIXI.Texture.from('../uploads/tiles/' + tile.tile_image);
    tileTextures[`${tile.x},${tile.y}`] = texture;

    // Создаем спрайт тайла
    const sprite = new PIXI.Sprite(texture);
    sprite.x = tile.x * tileSize;
    sprite.y = tile.y * tileSize;
    sprite.width = tileSize;
    sprite.height = tileSize;
    sprite.tileKey = `${tile.x},${tile.y}`;
    mapContainer.addChild(sprite); // Добавляем спрайт на карту

    // Если тайл блокирующий, накладываем полупрозрачный красный блок
    if (tile.is_blocking) {
        const blockOverlay = new PIXI.Graphics();
        blockOverlay.beginFill(0xff0000, 0.5); // Полупрозрачный красный цвет для блокирующих тайлов
        blockOverlay.drawRect(tile.x * tileSize, tile.y * tileSize, tileSize, tileSize);
        blockOverlay.endFill();
        mapContainer.addChild(blockOverlay);
    }
});

// Обработчик выбора тайла
document.querySelectorAll('.tile').forEach(tile => {
    tile.addEventListener('click', function () {
        selectedTexture = PIXI.Texture.from(this.src); // Устанавливаем текстуру выбранного тайла
        const tileImageName = this.src.split('/').pop(); // Извлекаем только имя файла
        selectedTexture.imageName = tileImageName; // Сохраняем имя файла в объекте текстуры
        console.log("Выбрана текстура:", tileImageName);
    });
});

// Функция для размещения тайлов в области
function fillArea(startX, startY, endX, endY) {
    const xStart = Math.min(startX, endX);
    const xEnd = Math.max(startX, endX);
    const yStart = Math.min(startY, endY);
    const yEnd = Math.max(startY, endY);

    for (let x = xStart; x <= xEnd; x++) {
        for (let y = yStart; y <= yEnd; y++) {
            placeTile(x, y); // Рисуем тайл на каждой клетке в выделенной области
        }
    }
}

// Функция для размещения тайла на карте по координатам
function placeTile(x, y) {
    const tileKey = `${x},${y}`;

    if (!selectedTexture) {
        console.log("Текстура не выбрана. Невозможно разместить тайл.");
        return;
    }

    // Удаляем старый тайл, если он уже существует
    if (tileTextures[tileKey]) {
        const existingTile = mapContainer.children.find(child => child.tileKey === tileKey);
        if (existingTile) {
            mapContainer.removeChild(existingTile);
        }
    }

    // Создаем и добавляем новый тайл
    const sprite = new PIXI.Sprite(selectedTexture);
    sprite.x = x * tileSize;
    sprite.y = y * tileSize;
    sprite.width = tileSize;
    sprite.height = tileSize;
    sprite.tileKey = tileKey;
    mapContainer.addChild(sprite);
    tileTextures[tileKey] = selectedTexture;

    console.log("Тайл размещен на координатах X:", x, "Y:", y);

    // Если включен флажок блокировки, добавляем визуальный индикатор
    if (blockingCheckbox.checked) {
        const blockOverlay = new PIXI.Graphics();
        blockOverlay.beginFill(0xff0000, 0.5); // Полупрозрачный красный цвет для блокировки
        blockOverlay.drawRect(x * tileSize, y * tileSize, tileSize, tileSize);
        blockOverlay.endFill();
        mapContainer.addChild(blockOverlay);
    }

    // Сохраняем тайл в базу данных с параметром блокировки
    saveTile(x, y, selectedTexture.imageName, blockingCheckbox.checked);
}

// Функция для сохранения тайла в базу данных
function saveTile(x, y, tileImage, isBlocking) {
    fetch('map_editor.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            x: x,
            y: y,
            tile_image: tileImage, // Теперь передаем только имя файла, например '1.png'
            is_blocking: isBlocking ? 1 : 0,  // Передаем значение флага блокировки
            save_tile: true
        }),
    }).then(response => response.json()).then(data => {
        console.log(data.message); // Выводим результат на консоль
    }).catch(err => {
        console.error('Ошибка при сохранении тайла:', err);
    });
}

// Функция для отображения координат клетки
function showTileCoordinates(e) {
    const rect = app.view.getBoundingClientRect();

    // Рассчитываем координаты клетки с учетом смещения карты
    const x = Math.floor((e.clientX - rect.left - mapContainer.x) / tileSize);
    const y = Math.floor((e.clientY - rect.top - mapContainer.y) / tileSize);

    // Отображаем координаты клетки
    coordinatesDisplay.style.display = 'block';
    coordinatesDisplay.textContent = `Coordinates: (${x}, ${y})`;

    // Располагаем элемент рядом с курсором
    coordinatesDisplay.style.left = `${e.clientX + 10}px`;
    coordinatesDisplay.style.top = `${e.clientY + 10}px`;
}

// Скрываем координаты при уходе курсора с карты
app.view.addEventListener('mouseleave', function () {
    coordinatesDisplay.style.display = 'none'; // Скрываем элемент
});

// Добавляем возможность перетаскивания карты правой кнопкой мыши
let dragging = false;
let previousPosition = null;

app.view.addEventListener('mousedown', function (event) {
    const rect = app.view.getBoundingClientRect();
    const x = Math.floor((event.clientX - rect.left - mapContainer.x) / tileSize);
    const y = Math.floor((event.clientY - rect.top - mapContainer.y) / tileSize);

    if (event.button === 0 && !event.ctrlKey) { // Левая кнопка мыши для рисования
        isDrawing = true;
        placeTile(x, y);
    }

    if (event.button === 2) { // Правая кнопка мыши для перемещения карты
        dragging = true;
        previousPosition = { x: event.clientX, y: event.clientY }; // Сохраняем начальную позицию
    }

    if (event.button === 0 && event.ctrlKey) { // Начинаем выделение области с Ctrl
        isSelecting = true;
        startX = x;
        startY = y;
    }
});

app.view.addEventListener('mouseup', function (event) {
    isDrawing = false; // Останавливаем рисование
    dragging = false; // Останавливаем перемещение карты

    if (isSelecting) {
        const rect = app.view.getBoundingClientRect();
        const x = Math.floor((event.clientX - rect.left - mapContainer.x) / tileSize);
        const y = Math.floor((event.clientY - rect.top - mapContainer.y) / tileSize);

        endX = x;
        endY = y;
        fillArea(startX, startY, endX, endY); // Закрашиваем область
        isSelecting = false;
    }
});

app.view.addEventListener('mousemove', function (event) {
    if (isDrawing) {
        const rect = app.view.getBoundingClientRect();
        const x = Math.floor((event.clientX - rect.left - mapContainer.x) / tileSize);
        const y = Math.floor((event.clientY - rect.top - mapContainer.y) / tileSize);
        placeTile(x, y); // Рисуем тайлы при перемещении мыши
    }

    if (dragging) {
        const dx = event.clientX - previousPosition.x; // Смещение по X
        const dy = event.clientY - previousPosition.y; // Смещение по Y

        mapContainer.x += dx; // Обновляем позицию контейнера
        mapContainer.y += dy; // Обновляем позицию контейнера

        previousPosition = { x: event.clientX, y: event.clientY }; // Обновляем предыдущую позицию
    }
});

// Отключаем контекстное меню при правом клике
app.view.addEventListener('contextmenu', function (e) {
    e.preventDefault(); // Отключаем стандартное поведение правой кнопки (контекстное меню)
});

// Добавляем управление клавишами для перемещения карты
const moveSpeed = 10; // Скорость перемещения карты

window.addEventListener('keydown', function (e) {
    switch (e.code) {
        case 'ArrowUp':
            mapContainer.y += moveSpeed; // Двигаем карту вниз (вверх)
            break;
        case 'ArrowDown':
            mapContainer.y -= moveSpeed; // Двигаем карту вверх (вниз)
            break;
        case 'ArrowLeft':
            mapContainer.x += moveSpeed; // Двигаем карту вправо (влево)
            break;
        case 'ArrowRight':
            mapContainer.x -= moveSpeed; // Двигаем карту влево (вправо)
            break;
    }
});

// Обработка перемещения мыши для отображения координат
app.view.addEventListener('mousemove', showTileCoordinates);

