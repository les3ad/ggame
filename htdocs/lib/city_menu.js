document.addEventListener('DOMContentLoaded', function () {
    const cityMenu = document.getElementById('cityMenu');
    const leaveCityButton = document.querySelector('.city-actions button');
    const cityButton = document.querySelector('.city-info');

    // Открытие меню города
    function toggleCityMenu() {
        if (cityMenu.style.display === 'none' || cityMenu.style.display === '') {
            cityMenu.style.display = 'block';  // Показываем меню города
        } else {
            cityMenu.style.display = 'none';   // Скрываем меню города
        }
    }

    // Закрытие меню города и возврат к карте
    function leaveCity() {
        cityMenu.style.display = 'none';   // Скрываем меню города
        alert('Вы покинули город. Возвращение к карте.');
    }

    // Привязка событий к кнопкам
    if (cityButton) {
        cityButton.addEventListener('click', toggleCityMenu);
    }

    if (leaveCityButton) {
        leaveCityButton.addEventListener('click', leaveCity);
    }
});
