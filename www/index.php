<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/answer_store.php';
function loadXmlFile($filename)
{
    if (!file_exists($filename)) {
        return new SimpleXMLElement('<?xml version="1.0"?><empty></empty>');
    }

    $content = file_get_contents($filename);
    if (empty($content)) {
        return new SimpleXMLElement('<?xml version="1.0"?><empty></empty>');
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
    if ($xml === false) {
        return new SimpleXMLElement('<?xml version="1.0"?><empty></empty>');
    }

    return $xml;
}

function ensureAnswerSession($filename, $variantId) {
    try {
        return withAnswersLock(function () use ($filename, $variantId) {
            recoverAnswersIfNeeded($filename, $variantId);
            if (!is_file($filename) || filesize($filename) === 0) {
                $document = createAnswersDocument($variantId);
                atomicWriteAnswers($filename, $document);
                return (int)$document->documentElement->getAttribute('started_at');
            }
            $document = new DOMDocument('1.0', 'UTF-8');
            if (!$document->load($filename, LIBXML_NONET)) {
                $archive = $filename . '.invalid.' . date('Ymd-His');
                if (!rename($filename, $archive)) return 0;
                $document = createAnswersDocument($variantId);
                atomicWriteAnswers($filename, $document);
                return (int)$document->documentElement->getAttribute('started_at');
            }
            $root = $document->documentElement;
            if ($root->getAttribute('variant_id') !== $variantId || $root->getAttribute('kind') !== 'student') {
                $archive = $filename . '.previous.' . date('Ymd-His');
                if (!rename($filename, $archive)) return 0;
                $document = createAnswersDocument($variantId);
                atomicWriteAnswers($filename, $document);
                return (int)$document->documentElement->getAttribute('started_at');
            }
            if ($root->getAttribute('started_at') === '') {
                $root->setAttribute('started_at', (string)time());
                atomicWriteAnswers($filename, $document);
            }
            return (int)$root->getAttribute('started_at');
        });
    } catch (Throwable $error) {
        return 0;
    }
}

function sanitizeTaskHtml($html) {
    $html = preg_replace('#<(script|iframe|object|embed|link|meta)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = preg_replace('#<(script|iframe|object|embed|link|meta)\b[^>]*/?>#is', '', $html);
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript):.*?\2/i', '', $html);
    return $html;
}

$manifest = loadXmlFile(__DIR__ . '/variant/manifest.xml');
$variantId = (string)$manifest->id;
$variantTitle = (string)$manifest->title;
$durationMinutes = max(1, (int)$manifest->duration_minutes);
$tasks = loadXmlFile(__DIR__ . '/variant/tasks.xml');
$startedAt = ensureAnswerSession(__DIR__ . '/variant/answers.xml', $variantId);
$answers = loadXmlFile(__DIR__ . '/variant/answers.xml');
$serverNow = time();
$examExpired = $startedAt > 0 && $serverNow >= $startedAt + ($durationMinutes * 60);

$startupErrors = [];
if ($startedAt <= 0) {
    $startupErrors[] = 'Не удалось создать или обновить answers.xml. Проверьте права записи в папку variant';
}
if ((string)$manifest['format_version'] !== '2' || $variantId === '') {
    $startupErrors[] = 'manifest.xml отсутствует или имеет неподдерживаемый формат';
}
if (is_file(__DIR__ . '/variant/answer_key.xml')) {
    $startupErrors[] = 'В ученической сборке обнаружен answer_key.xml. Удалите ключ ответов перед запуском';
}
if ((string)$tasks['variant_id'] !== $variantId) {
    $startupErrors[] = 'tasks.xml относится к другому варианту';
}

$taskIds = [];
$taskNumbers = [];
foreach ($tasks->task as $task) {
    $taskId = (string)$task->id;
    $taskNumber = (int)$task->number;
    if ($taskId === '' || isset($taskIds[$taskId])) {
        $startupErrors[] = 'Обнаружен пустой или повторяющийся task_id';
    }
    if ($taskNumber < 1 || isset($taskNumbers[$taskNumber])) {
        $startupErrors[] = 'Обнаружен некорректный или повторяющийся номер задания';
    }
    $taskIds[$taskId] = $taskNumber;
    $taskNumbers[$taskNumber] = true;
    if (isset($task->attachments)) {
        foreach ($task->attachments->file as $file) {
            $fileName = basename((string)$file);
            if ($fileName === '' || !is_file(__DIR__ . '/variant/files/' . $fileName)) {
                $startupErrors[] = "Не найдено вложение задания $taskNumber: $fileName";
            }
        }
    }
}
if ((int)$manifest->task_count !== count($taskIds)) {
    $startupErrors[] = 'Количество заданий не совпадает с manifest.xml';
}
if ($answers->getName() === 'answers') {
    if ((string)$answers['variant_id'] !== $variantId || (string)$answers['kind'] !== 'student') {
        $startupErrors[] = 'answers.xml относится к другому варианту или имеет неверный тип';
    }
    $seenAnswerIds = [];
    foreach ($answers->answer as $answer) {
        $answerId = (string)$answer['task_id'];
        $answerNumber = (int)$answer['number'];
        if ($answerId === '' || isset($seenAnswerIds[$answerId])) {
            $startupErrors[] = 'В answers.xml обнаружен пустой или повторяющийся task_id';
            continue;
        }
        $seenAnswerIds[$answerId] = true;
        if (!isset($taskIds[$answerId]) || $taskIds[$answerId] !== $answerNumber) {
            $startupErrors[] = 'В answers.xml обнаружен ответ на неизвестное задание';
        }
    }
}

if ($startupErrors) {
    http_response_code(500);
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка варианта</title>';
    echo '<body style="font:18px sans-serif;padding:30px"><h1>Вариант не может быть запущен</h1><ul>';
    foreach (array_unique($startupErrors) as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul></body></html>';
    exit;
}

$tasksArray = [];
foreach ($tasks->task as $task) {
    $tasksArray[] = $task;
}
usort($tasksArray, function ($a, $b) {
    return (int) $a->number - (int) $b->number;
});

$answersMap = [];
foreach ($answers->answer as $answer) {
    $answersMap[(string)$answer['task_id']] = (string)$answer->value;
}
?>

<!DOCTYPE html>

<html>

<head>
    <title><?= htmlspecialchars($variantTitle ?: 'Тест ЕГЭ') ?></title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="nav-wrapper">
        <div class="left-panel">
            <div class="timer-section">
                <time id="timer">03:50</time>
            </div>
            <div class="stats-section">
                <p>Дано ответов</p>
                <p id="existingAnswers">0/<?= count($tasksArray) ?></p>
            </div>
            <button type="button" class="scroll-btn" id="scrollUpBtn" onclick="scrollTasks(-1)" aria-label="Прокрутить задания вверх">↑</button>

            <div class="tasks-grid">
                <button class="task-btn i" onclick="showTask('i')"><p>
                    i
                </p></button>
                <?php foreach ($tasksArray as $task): ?>
                    <button class="task-btn <?= $task->number ?>" onclick="showTask(<?= (int) $task->number ?>)"><p>
                        <?= $task->number ?>
                    </p></button>
                <?php endforeach; ?>
            </div>

            <button type="button" class="scroll-btn" id="scrollDownBtn" onclick="scrollTasks(1)" aria-label="Прокрутить задания вниз">↓</button>
        </div>
    </div>
    <div class="wrapper" id="tasksWrapper">
        <div class="navigation-controls">
            <button id="prevBtn" onclick="navigateTask(-1)">◀ Назад</button>
            <button id="nextBtn" onclick="navigateTask(1)">Вперед ▶</button>
        </div>
        <div class="task-box task-i" id="taskBox-i">
            <div class="task-title"></div>
            <div class="task-number" style="display: none;">i</div>
            <div class="task-id" style="display: none;">i</div>
            <h2>Инструкция по выполнению работы</h2>
            <p>Экзаменационная работа состоит из 27 заданий с кратким ответом, выполняемых с помощью компьютера. На выполнение работы отводится 3 часа 55 минут (235 минут).</p>
            <p>При выполнении заданий доступны текстовый редактор, редактор электронных таблиц и системы программирования. Доступ к сети Интернет запрещён.</p>
            <p>Файлы, необходимые для выполнения заданий, находятся рядом с условием. Нажмите на имя файла, чтобы скачать его в рабочую папку.</p>
            <h2>Используемые соглашения</h2>
            <p data-v-0e9368a9="">В заданиях используются следующие соглашения.</p><p data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">1</span> Обозначения для логических связок (операций):<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">a)</span> отрицание (инверсия, логическое НЕ) обозначается ¬ (например, ¬А);<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">b)</span> конъюнкция (логическое умножение, логическое И) обозначается ∧ (например, А ∧ В) либо &amp; (например, А &amp; В);<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">c)</span> дизъюнкция (логическое сложение, логическое ИЛИ) обозначается ∨ (например, А ∨ В) либо | (например, А | В);<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">d)</span> следование (импликация) обозначается → (например, А → В);<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">e)</span> тождество обозначается ≡ (например, A ≡ B); выражение A ≡ B истинно тогда и только тогда, когда значения A и B совпадают (либо они оба истинны, либо они оба ложны);<br data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">f)</span> символ 1 используется для обозначения истины (истинного высказывания); символ 0 – для обозначения лжи (ложного высказывания). </p><p data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">2</span> Два логических выражения, содержащие переменные, называются равносильными (эквивалентными), если значения этих выражений совпадают при любых значениях переменных. Так, выражения А → В и (¬А) ∨ В равносильны, а А ∨ В и А ∧ В неравносильны (значения выражений разные, например, при А = 1, В = 0). </p><p data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">3</span> Приоритеты логических операций: инверсия (отрицание), конъюнкция (логическое умножение), дизъюнкция (логическое сложение), импликация (следование), тождество. Таким образом, ¬А ∧ В ∨ С ∧ D означает то же, что и ((¬А) ∧ В) ∨ (С ∧ D). Возможна запись А ∧ В ∧ С вместо (А ∧ В) ∧ С. То же относится и к дизъюнкции: возможна запись А ∨ В ∨ С вместо (А ∨ В) ∨ С. </p><p data-v-0e9368a9=""><span style="font-weight:600;" data-v-0e9368a9="">4</span> Обозначения Мбайт и Кбайт используются в традиционном для информатики смысле – как обозначения единиц измерения, соотношение которых с единицей «байт» выражается степенью двойки. </p><br data-v-0e9368a9="">
            <div class="answer" style="display: none;">
                        <input type="number" class="answer-input"
                            value="">
                    <input type="button" class="clear-button" style="display: none;" value="X">
                    <input type="button" class="save-btn" value="Сохранить">
                </div>
        </div>
        <?php foreach ($tasksArray as $task): ?>
            <div class="task-box task-<?= (int) $task->number ?>" id="taskBox-<?= (int) $task->number ?>"
                style="display: none;">
                <div class="task-title"><?= htmlspecialchars((string) $task->title) ?></div>
                <div class="task-number" style="display: none;"><?= (int) $task->number ?></div>
                <div class="task-id" style="display: none;"><?= htmlspecialchars((string) $task->id) ?></div>

                <?php if (!empty($task->html)): ?>
                    <div class="task-html">
                        <?php
                        // Получаем содержимое HTML тега
                        $htmlContent = sanitizeTaskHtml((string)$task->html);

                        // SimpleXML уже декодирует единственный уровень XML-сущностей.

                        // Обрабатываем теги изображений (если используются теги <image>)
                        if (strpos($htmlContent, '<image>') !== false) {
                            $htmlContent = preg_replace_callback(
                                '/<image>(.*?)<\/image>/',
                                function ($matches) {
                                    $src = trim($matches[1]);
                                    return '<div class="task-image">'
                                        . '<img src="variant/files/' . $src . '" alt="Иллюстрация к задаче">'
                                        . '</div>';
                                },
                                $htmlContent
                            );
                        }

                        // Обрабатываем обычные теги img (если они уже есть в HTML)
                        $htmlContent = preg_replace_callback(
                            '/<img\s+[^>]*src="([^"]*)"[^>]*>/',
                            function ($matches) {
                                $src = trim($matches[1]);
                                if (!preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $src)) {
                                    $src = ltrim($src, '/');
                                    if (strpos($src, 'variant/files/') !== 0) {
                                        $src = 'variant/files/' . basename($src);
                                    }
                                    return str_replace($matches[1], $src, $matches[0]);
                                }
                                return $matches[0];
                            },
                            $htmlContent
                        );

                        // Обрабатываем ссылки на файлы
                        $htmlContent = preg_replace_callback(
                            '/<a\s+[^>]*href="([^"]*)"[^>]*>/',
                            function ($matches) {
                                $href = trim($matches[1]);
                                if ($href !== '' && $href[0] !== '#' && !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $href)) {
                                    $fileName = basename(str_replace('\\', '/', $href));
                                    $downloadUrl = 'download.php?file=' . rawurlencode($fileName);
                                    return str_replace($matches[1], $downloadUrl, $matches[0]);
                                }
                                return $matches[0];
                            },
                            $htmlContent
                        );

                        // ВЫВОДИМ БЕЗ ЭКРАНИРОВАНИЯ
                        echo $htmlContent;
                        ?>
                    </div>
                <?php endif; ?>

                <?php
                // Обработка дополнительных файлов (может быть несколько)
                $extraFiles = [];

                if (isset($task->attachments)) {
                    foreach ($task->attachments->file as $file) {
                        $extraFiles[] = (string)$file;
                    }
                }

                if (!empty($extraFiles)): ?>
                    <div class="extra-files">
                        <?php foreach ($extraFiles as $file): ?>
                            <div class="extra-file">
                                <a href="download.php?file=<?= rawurlencode(basename($file)) ?>"><?= htmlspecialchars($file) ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="answer">
                    <?php if ($task->answer_type == 'table'): ?>
                        <?php
                        $tableHeight = isset($task->table_rows) ? (int) $task->table_rows : 0;
                        $tableWidth = isset($task->table_columns) ? (int) $task->table_columns : 0;

                        // Получаем сохраненные ответы для этого задания
                        $savedAnswers = [];
                        $taskId = (string)$task->id;
                        if (isset($answersMap[$taskId])) {
                            $savedAnswers = explode(';', $answersMap[$taskId]);
                        }

                        echo '<table class="answer-table">';
                        $cellIndex = 0;
                        for ($i = 0; $i < $tableHeight; $i++) {
                            echo '<tr>';
                            for ($j = 0; $j < $tableWidth; $j++) {
                                $cellValue = isset($savedAnswers[$cellIndex]) ? htmlspecialchars($savedAnswers[$cellIndex]) : '';
                                echo '<td><input type="number" class="answer-input" data-row="' . $i . '" data-col="' . $j . '" value="' . $cellValue . '"></td>';
                                $cellIndex++;
                            }
                            echo '</tr>';
                        }
                        echo '</table>';
                        ?>
                    <?php else: ?>
                        <input type="<?= ($task->answer_type == 'number') ? 'number' : 'text' ?>" class="answer-input"
                            value="<?= isset($answersMap[(string)$task->id]) ? htmlspecialchars($answersMap[(string)$task->id]) : '' ?>">

                    <?php endif; ?>
                    <input type="button" class="clear-button" style="display: none;" value="X">
                    <input type="button" class="save-btn" value="Сохранить">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        window.examConfig = <?= json_encode([
            'variantId' => $variantId,
            'durationSeconds' => $durationMinutes * 60,
            'startedAtMs' => $startedAt * 1000,
            'serverNowMs' => time() * 1000,
            'expired' => $examExpired,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="script.js"></script>
</body>

</html>
