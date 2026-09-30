<?php
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
    $xml = simplexml_load_string($content);
    if ($xml === false) {
        return new SimpleXMLElement('<?xml version="1.0"?><empty></empty>');
    }

    return $xml;
}

$tasks = loadXmlFile('variant/tasks.xml');
$answers = loadXmlFile('variant/answers.xml');

$tasksArray = [];
foreach ($tasks->task as $task) {
    $tasksArray[] = $task;
}
usort($tasksArray, function ($a, $b) {
    return (int) $a->number - (int) $b->number;
});

$answersMap = [];
foreach ($answers->answer as $answer) {
    $answersMap[(int) $answer->number] = (string) $answer->value;
}
?>

<!DOCTYPE html>

<html>

<head>
    <title>Тест ЕГЭ</title>
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
                <p id="existingAnswers">0/27</p>
            </div>
            <div class="scroll-btn" id="scrollUpBtn" onclick="scrollTasks(-10)"><p>↑</p></div>

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

            <div class="scroll-btn" id="scrollDownBtn" onclick="scrollTasks(10)"><p>↓</p></div>
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
                        $htmlContent = (string)$task->html;
                        
                        // Декодируем HTML-сущности ДВАЖДЫ (так как было двойное экранирование)
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        $htmlContent = htmlspecialchars_decode($htmlContent);
                        
                        // Выводим отладочную информацию
                        // echo "<!-- RAW: " . htmlspecialchars($htmlContent) . " -->";
                        
                        // Обрабатываем теги изображений (если используются теги <image>)
                        if (strpos($htmlContent, '<image>') !== false) {
                            $htmlContent = preg_replace_callback(
                                '/<image>(.*?)<\/image>/',
                                function ($matches) {
                                    $src = trim($matches[1]);
                                    return '<div class="task-image">'
                                        . '<img src="variant/' . $src . '" alt="Иллюстрация к задаче">'
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
                                // Если путь не содержит 'variant/', добавляем его
                                if (strpos($src, 'variant/') === false && 
                                    strpos($src, 'http') !== 0 &&
                                    strpos($src, '//') !== 0) {
                                    return str_replace($matches[1], 'variant/' . $matches[1], $matches[0]);
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
                                // Если путь не содержит 'variant/' и это не внешняя ссылка, добавляем
                                if (strpos($href, 'variant/') === false && 
                                    strpos($href, 'http') !== 0 &&
                                    strpos($href, '//') !== 0) {
                                    return str_replace($matches[1], 'variant/' . $matches[1], $matches[0]);
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

                foreach ($task->children() as $child) {
                    if ($child->getName() == 'extra_file') {
                        $extraFiles[] = (string) $child;
                    }
                }

                if (!empty($extraFiles)): ?>
                    <div class="extra-files">
                        <?php foreach ($extraFiles as $file): ?>
                            <div class="extra-file">
                                <a href="variant/<?= htmlspecialchars($file) ?>" download><?= htmlspecialchars($file) ?></a>
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
                        if (isset($answersMap[(int) $task->number])) {
                            $savedAnswers = explode(';', $answersMap[(int) $task->number]);
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
                            value="<?= isset($answersMap[(int) $task->number]) ? htmlspecialchars($answersMap[(int) $task->number]) : '' ?>">

                    <?php endif; ?>
                    <input type="button" class="clear-button" style="display: none;" value="X">
                    <input type="button" class="save-btn" value="Сохранить">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script src="script.js"></script>
</body>

</html>