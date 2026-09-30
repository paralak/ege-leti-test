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
if (($_POST['confirm'] ?? '') !== 'RESET') {
    respond(['status' => 'error', 'message' => 'Сброс не подтверждён'], 400);
}

libxml_use_internal_errors(true);
$manifest = simplexml_load_file(__DIR__ . '/variant/manifest.xml', 'SimpleXMLElement', LIBXML_NONET);
$variantId = $manifest !== false ? (string)$manifest->id : '';
if ($variantId === '') {
    respond(['status' => 'error', 'message' => 'Не удалось определить вариант'], 500);
}

try {
    withAnswersLock(function () use ($variantId) {
        atomicWriteAnswers(__DIR__ . '/variant/answers.xml', createAnswersDocument($variantId));
    });
    respond(['status' => 'success']);
} catch (Throwable $error) {
    respond(['status' => 'error', 'message' => $error->getMessage()], 500);
}
