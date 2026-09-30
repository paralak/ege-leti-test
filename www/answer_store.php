<?php

function withAnswersLock(callable $callback) {
    $lockFile = __DIR__ . '/variant/.answers.lock';
    $handle = fopen($lockFile, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Не удалось заблокировать файл ответов');
    }

    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function writeFileCompletely($filename, $content) {
    $handle = fopen($filename, 'xb');
    if ($handle === false) {
        throw new RuntimeException('Не удалось создать временный файл ответов');
    }

    try {
        $written = 0;
        $length = strlen($content);
        while ($written < $length) {
            $chunk = fwrite($handle, substr($content, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Не удалось полностью записать ответы');
            }
            $written += $chunk;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Не удалось сохранить ответы на диск');
        }
    } finally {
        fclose($handle);
    }
}

function atomicWriteAnswers($filename, DOMDocument $document) {
    $content = $document->saveXML();
    if ($content === false) {
        throw new RuntimeException('Не удалось сформировать XML ответов');
    }

    $temporary = $filename . '.tmp.' . bin2hex(random_bytes(6));
    $backup = $filename . '.bak.' . bin2hex(random_bytes(6));
    writeFileCompletely($temporary, $content);

    $hadOriginal = is_file($filename);
    try {
        if ($hadOriginal && !rename($filename, $backup)) {
            throw new RuntimeException('Не удалось подготовить замену файла ответов');
        }
        if (!rename($temporary, $filename)) {
            if ($hadOriginal) {
                @rename($backup, $filename);
            }
            throw new RuntimeException('Не удалось заменить файл ответов');
        }
        if ($hadOriginal) {
            @unlink($backup);
        }
    } catch (Throwable $error) {
        @unlink($temporary);
        if (!is_file($filename) && is_file($backup)) {
            @rename($backup, $filename);
        }
        throw $error;
    }
}

function createAnswersDocument($variantId, $startedAt = null) {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->formatOutput = true;
    $root = $document->appendChild($document->createElement('answers'));
    $root->setAttribute('variant_id', $variantId);
    $root->setAttribute('kind', 'student');
    $root->setAttribute('started_at', (string)($startedAt ?: time()));
    return $document;
}

