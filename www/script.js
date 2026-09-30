function initApp() {
    setupSaveBtn();
    setupClrBtn();
    setupAnswerInputs();
    initTimer();
    initNavigation();
    loadCountAnswer();
    loadDoneBtnTasks();
}

function loadDoneBtnTasks() {
    document.querySelectorAll('.task-box').forEach(task => {
        const answerInput = task.querySelectorAll('.answer-input');
        const answerArrayInput = Array.from(answerInput).map(ans => ans.value.trim());
        if (answerArrayInput.some(ans => ans !== '')) {
            updateDoneTaskButton(task.querySelector('.task-number').innerText);
        }
    });
}

function loadCountAnswer() {
    answersCount = Array.from(document.querySelectorAll('.task-box:not(.task-i)'))
        .filter(task => Array.from(task.querySelectorAll('.answer-input'))
            .some(input => input.value.trim() !== ''))
        .length;
    updateAnswersCountLabel();
}

function initTimer() {
    // Таймер на 3 часа 50 минут
    let totalSeconds = 3 * 3600 + 50 * 60;
    const timerElem = document.getElementById('timer');

    let startTime = localStorage.getItem('examStartTime');
    if (!startTime) {
        startTime = Date.now();
        localStorage.setItem('examStartTime', startTime);
    } else {
        startTime = parseInt(startTime);
    }

    function updateTimer() {
        const curTime = Date.now();
        const elapsedTime = Math.floor(curTime - startTime) / 1000
        const remainTime = Math.max(0, totalSeconds - elapsedTime);

        let hours = Math.floor(remainTime / 3600);
        let minutes = Math.floor((remainTime % 3600) / 60);
        timerElem.textContent =
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0');

        return remainTime;
    }

    const timerInterval = setInterval(() => {
        const remaining = updateTimer();
        if (remaining === 0) {
            timerElem.textContent = "Время вышло!";
            clearInterval(timerInterval);
        }
    }, 1000);
    updateTimer();
}

function setupSaveBtn() {
    document.querySelectorAll('.task-box').forEach(item => {
        item.querySelector('.save-btn').addEventListener('click', () => { saveAnswer(item) });
    });
}

function saveAnswer(task) {
    // Извлекаем необходимые данные из элементов
    const number = task.querySelector('.task-number').innerText;
    const taskID = task.querySelector('.task-id').innerText;
    const answerInputs = task.querySelectorAll('.answer-input');

    const curAnswer = Array.from(answerInputs).map(ans => ans.value.trim());
    const hasCurValue = curAnswer.some(value => value !== '');

    // const hasPastValue = savedAnswers.has(number);
    const formDataLoad = new FormData();
    formDataLoad.append('taskID', taskID);
    formDataLoad.append('number', number);
    formDataLoad.append('action', 'load');
    let hasPastValue = false;
    fetch('saveAnswer.php', {
        method: 'POST',
        body: formDataLoad
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.answer) {
                console.log(data.answer);
                if (data.answer !== '') {
                    hasPastValue = true;
                } else {
                    hasPastValue = false;
                }
            }

            console.log(hasPastValue)

            const formData = new FormData();
            formData.append('taskID', taskID);
            formData.append('number', number);
            // Собираем значения всех input'ов
            formData.append('answer', curAnswer.join(';'));

            // Отправляем POST-запрос
            fetch('saveAnswer.php', { // Укажите правильный путь к вашему PHP-файлу
                method: 'POST',
                body: formData
                // Не нужно указывать Content-Type - браузер установит его автоматически с boundary
            })
                .then(response => response.json())
                .then(data => {
                    console.log(hasPastValue)
                    if (data.status === 'success') {
                        let doneUpdate = true
                        if (hasCurValue && !hasPastValue) {
                            increaseAnswersCount();
                        } else if (!hasCurValue && hasPastValue) {
                            decreaseAnswersCount();
                            removeDoveTaskButton(number);
                            rmSavedFlag(task);
                            doneUpdate = false
                        }

                        if (doneUpdate) {
                            setSavedFlag(task)
                            updateDoneTaskButton(number);
                        }

                        updateClearButtonVisibility(task);
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                })
        });
}

function setSavedFlag(task) {
    saveBtn = task.querySelector('.save-btn');
    saveBtn.classList.add('saved');
    saveBtn.value = 'Сохранено'
}

function rmSavedFlag(task) {
    saveBtn = task.querySelector('.save-btn');
    saveBtn.classList.remove('saved');
    saveBtn.value = 'Сохранить'
}

function setupClrBtn() {
    document.querySelectorAll('.task-box').forEach((item, i) => {
        const e = item
        item.querySelector('.clear-button').addEventListener('click', () => { clearAnswer(e) });
    });
}

function clearAnswer(task) {
    const answerInputs = task.querySelectorAll('.answer-input');
    answerInputs.forEach(input => {
        input.value = '';
    });
    updateClearButtonVisibility(task);
}

function updateClearButtonVisibility(task) {
    const answerInputs = task.querySelectorAll('.answer-input');
    const clearButton = task.querySelector('.clear-button');

    let anse = false;

    answerInputs.forEach(input => {
        if (input.value.trim() !== '') {
            anse = true;
        }
    })

    if (anse) {
        clearButton.style.display = 'inline-block';
    } else {
        clearButton.style.display = 'none';
    }
}

function setupAnswerInputs() {
    document.querySelectorAll('.task-box').forEach((task) => {
        const answerInput = task.querySelectorAll('.answer-input');

        // Устанавливаем начальную видимость кнопки очистки
        updateClearButtonVisibility(task);

        answerInput.forEach(input => {
            input.addEventListener('input', () => {
                updateClearButtonVisibility(task);
            });

            // Блокируем стрелки клавиатуры для полей типа number
            if (input.type === 'number') {
                input.addEventListener('keydown', (e) => {
                    // Блокируем стрелки вверх (38) и вниз (40)
                    if (e.keyCode === 38 || e.keyCode === 40) {
                        e.preventDefault();
                    }
                });

                // Блокируем колесико мыши
                input.addEventListener('wheel', (e) => {
                    e.preventDefault();
                });
            }
        })
    });
}

window.onload = function () {
    const firstTask = document.querySelector('.task-box');
    if (firstTask) {
        showTask("i");
        // Загружаем ответы для первой задачи
        const firstTaskNumber = firstTask.querySelector('.task-number').innerText;
        loadAnswersForTask(firstTask, firstTaskNumber);
    }
};

function showTask(taskNumber) {
    // Скрываем все задачи
    document.querySelectorAll('.task-box').forEach(box => {
        box.style.display = 'none';
    });

    // Показываем выбранную задачу
    const taskBox = document.getElementById('taskBox-' + taskNumber);
    if (taskBox) {
        taskBox.style.display = 'block';
        // Загружаем ответы для этой задачи
        if (taskNumber !== 'i')
            loadAnswersForTask(taskBox, taskNumber);

        // Обновляем текущий индекс
        currentTaskIndex = taskNumbers.indexOf(taskNumber);

        // Убеждаемся, что кнопка видна в списке
        ensureTaskVisible(taskNumber);

        // Обновляем активную кнопку и состояние навигации
        updateActiveTaskButton(taskNumber);
        updateNavigationButtons();
    }
}

function loadAnswersForTask(taskBox, taskNumber) {
    // Получаем taskID из элемента
    const taskID = taskBox.querySelector('.task-id').innerText;

    // Отправляем запрос для получения ответа
    const formData = new FormData();
    formData.append('taskID', taskID);
    formData.append('number', taskNumber);
    formData.append('action', 'load');

    fetch('saveAnswer.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.answer) {
                // Загружаем ответ в поля ввода
                const answerInputs = taskBox.querySelectorAll('.answer-input');

                if (answerInputs.length === 1) {
                    // Обычное поле ввода
                    answerInputs[0].value = data.answer;
                } else if (answerInputs.length > 1) {
                    // Табличные поля - разбиваем по точке с запятой
                    const answers = data.answer.split(';');
                    answerInputs.forEach((input, index) => {
                        input.value = answers[index] || '';
                    });
                }

                // Обновляем видимость кнопки очистки
                updateClearButtonVisibility(taskBox);
            }
        })
        .catch(error => {
            console.log('Ответ не найден или ошибка загрузки:', error);
        });
}

let currentTaskIndex = 0;
let taskNumbers = [];

function initNavigation() {
    // Получаем все номера заданий в отсортированном порядке
    const taskButtons = document.querySelectorAll('.task-btn');
    taskNumbers = Array.from(taskButtons).map(btn => {
        const value = btn.textContent.trim();
        return value === 'i' ? 'i' : parseInt(value, 10);
    });

    // Устанавливаем текущий индекс на первое задание
    currentTaskIndex = 0;

    // Инициализируем прокрутку
    updateScrollButtons()
    // Обновляем состояние кнопок навигации
    updateNavigationButtons();
}

function navigateTask(direction) {
    // Вычисляем новый индекс
    const newIndex = currentTaskIndex + direction;

    // Проверяем границы
    if (newIndex >= 0 && newIndex < taskNumbers.length) {
        currentTaskIndex = newIndex;
        const taskNumber = taskNumbers[currentTaskIndex];

        // Показываем задание
        showTask(taskNumber);

        // Обновляем активную кнопку в навигации
        updateActiveTaskButton(taskNumber);

        // Обновляем состояние кнопок навигации
        updateNavigationButtons();
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    // Отключаем кнопку "Назад" если мы на первом задании
    prevBtn.disabled = currentTaskIndex === 0;

    // Отключаем кнопку "Вперед" если мы на последнем задании
    nextBtn.disabled = currentTaskIndex === taskNumbers.length - 1;
}

function removeFlag(flag) {
    document.querySelectorAll('.task-btn').forEach(btn => {
        btn.classList.remove(flag);
    });
}

function updateActiveTaskButton(taskNumber) {
    // Убираем активный класс у всех кнопок
    removeFlag('active');

    // Добавляем активный класс к текущей кнопке
    document.querySelectorAll('.task-btn').forEach(btn => {
        if (parseInt(btn.textContent) === parseInt(taskNumber) || taskNumber === btn.textContent) {
            btn.classList.add('active');
        }
    });
}

function updateDoneTaskButton(taskNumber) {
    // Добавляем активный класс к текущей кнопке
    document.querySelectorAll('.task-btn').forEach(btn => {
        if (parseInt(btn.textContent) === parseInt(taskNumber)) {
            btn.classList.add('done');
        }
    });
}

function removeDoveTaskButton(taskNumber) {
    document.querySelectorAll('.task-btn').forEach(btn => {
        if (parseInt(btn.textContent) === parseInt(taskNumber)) {
            btn.classList.remove('done');
        }
    });
}


function updateScrollButtons() {
    const scrollUpBtn = document.getElementById('scrollUpBtn');
    const scrollDownBtn = document.getElementById('scrollDownBtn');
    const container = document.querySelector('.tasks-grid');

    // Отключаем кнопку "вверх" если мы в начале списка
    scrollUpBtn.disabled = container.scrollTop <= 1;
    // Отключаем кнопку "вниз" если мы в конце списка
    scrollDownBtn.disabled = container.scrollTop + container.clientHeight >= container.scrollHeight - 1;
}

function scrollTasks(direction) {
    const container = document.querySelector('.tasks-grid');
    const distance = Math.max(1, Math.floor(container.clientHeight * 0.8));
    container.scrollBy({
        top: direction * distance,
        behavior: 'smooth'
    });

    // Обновляем состояние кнопок после прокрутки
    setTimeout(() => {
        updateScrollButtons();
    }, 100);
}

function ensureTaskVisible(taskNumber) {
    const container = document.querySelector('.tasks-grid');
    const targetButton = Array.from(container.querySelectorAll('.task-btn')).find(btn => {
        const value = btn.textContent.trim();
        return value === String(taskNumber);
    });

    if (!targetButton) return;

    const containerRect = container.getBoundingClientRect();
    const buttonRect = targetButton.getBoundingClientRect();

    if (buttonRect.top < containerRect.top) {
        container.scrollBy({
            top: buttonRect.top - containerRect.top,
            behavior: 'smooth'
        });
    } else if (buttonRect.bottom > containerRect.bottom) {
        container.scrollBy({
            top: buttonRect.bottom - containerRect.bottom,
            behavior: 'smooth'
        });
    }

    // Обновляем состояние кнопок после прокрутки
    setTimeout(() => {
        updateScrollButtons();
    }, 300);
}

document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.tasks-grid');
    if (container) {
        container.addEventListener('scroll', function () {
            updateScrollButtons();
        });
        window.addEventListener('resize', updateScrollButtons);
    }
});

let answersCount = 0;

function updateAnswersCountLabel() {
    const totalTasks = document.querySelectorAll('.task-box:not(.task-i)').length;
    document.getElementById('existingAnswers').textContent = answersCount + '/' + totalTasks;
}

function increaseAnswersCount() {
    const totalTasks = document.querySelectorAll('.task-box:not(.task-i)').length;
    answersCount = Math.min(totalTasks, answersCount + 1);
    updateAnswersCountLabel();
}

function decreaseAnswersCount() {
    answersCount = Math.max(0, answersCount - 1);
    updateAnswersCountLabel();
}

document.addEventListener('DOMContentLoaded', initApp);
