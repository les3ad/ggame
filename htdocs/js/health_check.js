// Таймер для проверки здоровья каждую минуту
setInterval(function() {
    updatePlayerHealth();
}, 60); // 1 минута

function updatePlayerHealth() {
    fetch('lib/update_health.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Ошибка сети');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log('Здоровье обновлено: ' + data.health); // Отладка
        } else {
            console.error('Ошибка обновления здоровья: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
    });
}
