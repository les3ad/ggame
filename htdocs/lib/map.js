const tileSize = 80;
let playerX;
let playerY;
let mapContainer;
let app;
let playerHealth;
let playerMaxHealth;
let resources = []; // Ресурсы на карте
let professions = {}; // Профессии игрока

function initMap(pX, pY, tiles, health, maxHealth, mapResources, playerProfessions) {
    playerX = pX;
    playerY = pY;
    playerHealth = health;
    playerMaxHealth = maxHealth;
    resources = mapResources;
    professions = playerProfessions;

    const mapWidth = window.innerWidth;
    const mapHeight = window.innerHeight;

    app = new PIXI.Application({ width: mapWidth, height: mapHeight });
    document.getElementById('game-container').appendChild(app.view);

    app.view.style.margin = '0';
    app.view.style.padding = '0';
    app.view.style.display = 'flex';  
    app.view.style.justifyContent = 'center';
    app.view.style.alignItems = 'center';
    app.view.style.width = '100vw'; 
    app.view.style.height = '100vh'; 
    app.renderer.resize(mapWidth, mapHeight);

    mapContainer = new PIXI.Container();
    app.stage.addChild(mapContainer);

    drawMap(tiles);
    drawPlayer();
    drawResources();
}

function drawMap(tiles) {
    mapContainer.removeChildren();
    tiles.forEach(tile => {
        const texture = PIXI.Texture.from('../uploads/tiles/' + tile.tile_image + '?v=1.2');
        const sprite = new PIXI.Sprite(texture);
        
        sprite.x = (tile.x - playerX + Math.floor((window.innerWidth / tileSize) / 2)) * tileSize;
        sprite.y = (tile.y - playerY + Math.floor((window.innerHeight / tileSize) / 2)) * tileSize;
        sprite.width = tileSize;
        sprite.height = tileSize;

        if (Math.abs(tile.x - playerX) <= 1 && Math.abs(tile.y - playerY) <= 1) {
            sprite.tint = 0x00ff00;
            sprite.interactive = true;
            sprite.buttonMode = true;

            const arrowTexture = getArrowTexture(tile.x, tile.y);
            if (arrowTexture) {
                const arrow = new PIXI.Sprite(arrowTexture);
                arrow.width = 16;
                arrow.height = 16;
                arrow.x = sprite.x + (tileSize - arrow.width) / 2;
                arrow.y = sprite.y + (tileSize - arrow.height) / 2;
                arrow.tint = 0x808080;
                mapContainer.addChild(arrow);
            }

            sprite.on('pointerdown', () => {
                handleTileClick(tile.x, tile.y);
            });
        }

        mapContainer.addChild(sprite);
    });
}

function drawResources() {
    resources.forEach(resource => {
        const texture = PIXI.Texture.from('../assets/resource.png');
        const sprite = new PIXI.Sprite(texture);

        sprite.x = (resource.x - playerX + Math.floor((window.innerWidth / tileSize) / 2)) * tileSize;
        sprite.y = (resource.y - playerY + Math.floor((window.innerHeight / tileSize) / 2)) * tileSize;
        sprite.width = tileSize;
        sprite.height = tileSize;

        sprite.interactive = true;
        sprite.buttonMode = true;

        sprite.on('pointerdown', () => {
            collectResource(resource);
        });

        mapContainer.addChild(sprite);
    });
}


function drawPlayer() {
    const playerSprite = PIXI.Sprite.from('../assets/player.png');
    playerSprite.width = tileSize;
    playerSprite.height = tileSize;

    playerSprite.x = Math.floor(window.innerWidth / tileSize / 2) * tileSize;
    playerSprite.y = Math.floor(window.innerHeight / tileSize / 2) * tileSize;

    mapContainer.addChild(playerSprite);
}

function handleTileClick(tileX, tileY) {
    // Сначала проверяем, находится ли игрок в бою
    fetch('check_battle_status.php', {  // Добавляем новый файл PHP для проверки статуса боя
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: playerX }) // Отправляем идентификатор пользователя
    })
    .then(response => response.json())
    .then(data => {
        if (data.in_battle) {
            alert('Вы находитесь в бою и не можете перемещаться!');
            return;
        }

        // Если игрок не в бою, продолжаем проверку клика
        if (Math.abs(tileX - playerX) <= 1 && Math.abs(tileY - playerY) <= 1) {
            fetch('check_tile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ x: tileX, y: tileY }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.can_move) {
                    fetch('update_player_position.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ x: tileX, y: tileY }),
                    })
                    .then(() => {
                        playerX = tileX;
                        playerY = tileY;
                        document.getElementById('playerX').textContent = playerX;
                        document.getElementById('playerY').textContent = playerY;
                        window.location.href = `game.php`;
                    });
                } else {
                    alert('Эта клетка заблокирована!');
                }
            });
        } else {
            alert('Можно двигаться только на соседнюю клетку!');
        }
    })
    .catch(error => {
        console.error('Ошибка проверки состояния боя:', error);
    });
}
function updateBattleStatus(isInBattle) {
    const battleIndicator = document.getElementById('battleIndicator');
    if (isInBattle) {
        battleIndicator.style.display = 'block'; // Показываем индикатор боя
        battleIndicator.textContent = 'Вы находитесь в бою!';
    } else {
        battleIndicator.style.display = 'none'; // Скрываем индикатор, если игрок не в бою
    }
}

// Вызываем эту функцию после проверки статуса боя
function checkBattleStatus() {
    fetch('../player/check_battle_status.php', {  
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: playerX })
    })
    .then(response => response.json())
    .then(data => {
        updateBattleStatus(data.in_battle);  // Обновляем UI в зависимости от статуса боя
    })
    .catch(error => {
        console.error('Ошибка проверки состояния боя:', error);
    });
}

// Вызываем проверку при загрузке карты
checkBattleStatus();


function getArrowTexture(tileX, tileY) {
    const arrowTextures = {
        up: PIXI.Texture.from('../assets/arrow_up.png'),
        down: PIXI.Texture.from('../assets/arrow_down.png'),
        left: PIXI.Texture.from('../assets/arrow_left.png'),
        right: PIXI.Texture.from('../assets/arrow_right.png')
    };

    if (tileX === playerX && tileY === playerY - 1) {
        return arrowTextures.up;
    } else if (tileX === playerX && tileY === playerY + 1) {
        return arrowTextures.down;
    } else if (tileX === playerX - 1 && tileY === playerY) {
        return arrowTextures.left;
    } else if (tileX === playerX + 1 && tileY === playerY) {
        return arrowTextures.right;
    }
    return null;
}

function collectResource(resource_id, x, y) {
    const payload = { resource_id: resource_id, x: x, y: y };

    fetch('collect_resource.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'game.php';
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
    });
}

function showModal() {
    document.getElementById('lowHealthModal').style.display = 'flex'; // Показываем модальное окно
}

function closeModal() {
    document.getElementById('lowHealthModal').style.display = 'none'; // Закрываем модальное окно
}
