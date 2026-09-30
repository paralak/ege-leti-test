<?php

$variantDir = realpath(__DIR__ . '/variant');
$requestedName = isset($_GET['file']) ? basename(str_replace('\\', '/', $_GET['file'])) : '';

if ($variantDir === false || $requestedName === '') {
    http_response_code(400);
    exit('Некорректное имя файла');
}

$filePath = realpath($variantDir . DIRECTORY_SEPARATOR . $requestedName);
$variantPrefix = rtrim($variantDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $filePath === false ||
    !is_file($filePath) ||
    strncmp($filePath, $variantPrefix, strlen($variantPrefix)) !== 0
) {
    http_response_code(404);
    exit('Файл не найден');
}

$mimeType = function_exists('mime_content_type')
    ? mime_content_type($filePath)
    : 'application/octet-stream';

header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($requestedName));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
