<?php
header('Content-Type: application/json; charset=UTF-8');

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

$manifest = simplexml_load_file(__DIR__ . '/variant/manifest.xml');
$variantId = $manifest !== false ? (string)$manifest->id : '';
if ($variantId === '') {
    respond(['status' => 'error', 'message' => 'Не удалось определить вариант'], 500);
}

$xmlFile = __DIR__ . '/variant/answers.xml';
$handle = fopen($xmlFile, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    respond(['status' => 'error', 'message' => 'Не удалось заблокировать файл ответов'], 500);
}

rewind($handle);
$content = stream_get_contents($handle);
$document = new DOMDocument('1.0', 'UTF-8');
$document->formatOutput = true;

if (trim($content) === '') {
    $root = $document->appendChild($document->createElement('answers'));
    $root->setAttribute('variant_id', $variantId);
    $root->setAttribute('kind', 'student');
} elseif (!$document->loadXML($content, LIBXML_NONET)) {
    flock($handle, LOCK_UN);
    fclose($handle);
    respond(['status' => 'error', 'message' => 'Файл ответов повреждён'], 500);
} else {
    $root = $document->documentElement;
    if ($root->getAttribute('variant_id') !== $variantId || $root->getAttribute('kind') !== 'student') {
        flock($handle, LOCK_UN);
        fclose($handle);
        respond(['status' => 'error', 'message' => 'Файл ответов относится к другому варианту'], 409);
    }
}

$existing = null;
foreach ($root->getElementsByTagName('answer') as $answer) {
    if ($answer->getAttribute('task_id') === $taskId) {
        $existing = $answer;
        break;
    }
}

if (($_POST['action'] ?? 'save') === 'load') {
    $value = '';
    if ($existing !== null) {
        $nodes = $existing->getElementsByTagName('value');
        $value = $nodes->length ? $nodes->item(0)->textContent : '';
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    respond(['status' => 'success', 'answer' => $value]);
}

if ($existing !== null) {
    $root->removeChild($existing);
}

$answer = $root->appendChild($document->createElement('answer'));
$answer->setAttribute('task_id', $taskId);
$answer->setAttribute('number', (string)$number);
$value = $answer->appendChild($document->createElement('value'));
$value->appendChild($document->createTextNode((string)($_POST['answer'] ?? '')));

$serialized = $document->saveXML();
rewind($handle);
ftruncate($handle, 0);
$written = 0;
$length = strlen($serialized);
while ($written < $length) {
    $chunk = fwrite($handle, substr($serialized, $written));
    if ($chunk === false || $chunk === 0) {
        break;
    }
    $written += $chunk;
}
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($written !== $length) {
    respond(['status' => 'error', 'message' => 'Не удалось полностью сохранить ответы'], 500);
}

respond(['status' => 'success', 'message' => 'Ответ сохранён']);
