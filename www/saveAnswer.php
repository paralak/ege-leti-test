<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/answer_store.php';

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Метод не поддерживается'], 405);
}

$number = filter_input(INPUT_POST, 'number', FILTER_VALIDATE_INT);
$taskId = isset($_POST['taskID']) ? trim($_POST['taskID']) : '';
if ($number === false || $number === null || $number < 1 || $taskId === '') {
    respond(['status' => 'error', 'message' => 'Некорректные данные задания'], 400);
}

$manifest = simplexml_load_file(__DIR__ . '/variant/manifest.xml', 'SimpleXMLElement', LIBXML_NONET);
$variantId = $manifest !== false ? (string)$manifest->id : '';
$durationSeconds = $manifest !== false ? max(60, (int)$manifest->duration_minutes * 60) : 0;
if ($variantId === '') {
    respond(['status' => 'error', 'message' => 'Не удалось определить вариант'], 500);
}

$tasks = simplexml_load_file(__DIR__ . '/variant/tasks.xml', 'SimpleXMLElement', LIBXML_NONET);
$validTask = false;
if ($tasks !== false && (string)$tasks['variant_id'] === $variantId) {
    foreach ($tasks->task as $task) {
        if ((string)$task->id === $taskId && (int)$task->number === $number) {
            $validTask = true;
            break;
        }
    }
}
if (!$validTask) {
    respond(['status' => 'error', 'message' => 'Задание не найдено в текущем варианте'], 404);
}

try {
    $result = withAnswersLock(function () use ($variantId, $taskId, $number, $durationSeconds) {
        $xmlFile = __DIR__ . '/variant/answers.xml';
        recoverAnswersIfNeeded($xmlFile, $variantId);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        if (!is_file($xmlFile) || filesize($xmlFile) === 0) {
            throw new RuntimeException('Попытка не начата. Вернитесь на страницу ввода номера КИМ');
        } elseif (!$document->load($xmlFile, LIBXML_NONET)) {
            throw new RuntimeException('Файл ответов повреждён');
        }
        $root = $document->documentElement;
        if ($root->getAttribute('variant_id') !== $variantId || $root->getAttribute('kind') !== 'student') {
            throw new RuntimeException('Файл ответов относится к другому варианту');
        }
        if (!preg_match('/^\d{1,32}$/D', $root->getAttribute('kim_number'))) {
            throw new RuntimeException('В попытке отсутствует корректный номер КИМ');
        }
        $startedAt = (int)$root->getAttribute('started_at');
        if ($startedAt <= 0 || time() > $startedAt + $durationSeconds + 30) {
            throw new RuntimeException('Время выполнения истекло, ответ не сохранён');
        }

        $existing = null;
        foreach ($root->getElementsByTagName('answer') as $answer) {
            if ($answer->getAttribute('task_id') === $taskId) {
                $existing = $answer;
                break;
            }
        }

        $hadAnswer = false;
        if ($existing !== null) {
            $existingValues = $existing->getElementsByTagName('value');
            $hadAnswer = $existingValues->length > 0 && trim($existingValues->item(0)->textContent) !== '';
            $root->removeChild($existing);
        }

        $newValue = trim((string)($_POST['answer'] ?? ''));
        if ($newValue !== '') {
            $answer = $root->appendChild($document->createElement('answer'));
            $answer->setAttribute('task_id', $taskId);
            $answer->setAttribute('number', (string)$number);
            $value = $answer->appendChild($document->createElement('value'));
            $value->appendChild($document->createTextNode($newValue));
        }

        atomicWriteAnswers($xmlFile, $document);
        return [
            'status' => 'success',
            'message' => 'Ответ сохранён',
            'hadAnswer' => $hadAnswer,
            'hasAnswer' => $newValue !== '',
        ];
    });
} catch (Throwable $error) {
    respond(['status' => 'error', 'message' => $error->getMessage()], 500);
}
respond($result);
