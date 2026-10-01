<?php

const ANSWER_SESSION_RESET_SECONDS = 86400;

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

    $transactionId = bin2hex(random_bytes(6));
    $temporary = $filename . '.tmp.' . $transactionId;
    $backup = $filename . '.bak.' . $transactionId;
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
        try {
            runOptionalAnswersCopy($filename, answersCopyFilename($document));
        } catch (Throwable $ignored) {
            // Резервное копирование необязательно и не должно мешать сохранению ответа.
        }
    } catch (Throwable $error) {
        @unlink($temporary);
        if (!is_file($filename) && is_file($backup)) {
            @rename($backup, $filename);
        }
        throw $error;
    }
}

function answersCopyFilename(DOMDocument $document) {
    $root = $document->documentElement;
    $kimNumber = $root !== null ? trim($root->getAttribute('kim_number')) : '';
    $safeKim = preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', $kimNumber);
    $safeKim = trim((string)$safeKim, " .\t\r\n");
    if ($safeKim === '') {
        $safeKim = 'unknown';
    }
    if (function_exists('mb_substr')) {
        $safeKim = mb_substr($safeKim, 0, 120, 'UTF-8');
    } else {
        $safeKim = substr($safeKim, 0, 120);
    }
    return 'answers_' . $safeKim . '.xml';
}

function runOptionalAnswersCopy($answersFilename, $targetFilename) {
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return;
    }

    $batchFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'copy_answers.bat';
    if (!is_file($batchFile) || !is_file($answersFilename)) {
        return;
    }

    // Без /B: дочерний cmd получает собственные дескрипторы и CGI не ждёт xcopy.
    $command = 'cmd.exe /D /C start "" /MIN '
        . escapeshellarg($batchFile) . ' '
        . escapeshellarg($answersFilename)
        . ' ' . escapeshellarg($targetFilename)
        . ' >NUL 2>&1';
    if (function_exists('exec')) {
        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);
        return;
    }

    if (function_exists('shell_exec')) {
        @shell_exec($command);
        return;
    }

    if (function_exists('popen')) {
        $process = @popen($command, 'r');
        if (is_resource($process)) {
            @pclose($process);
        }
    }
}

function recoverAnswersIfNeeded($filename, $variantId) {
    if (is_file($filename)) {
        return;
    }

    $backups = glob($filename . '.bak.*') ?: [];
    usort($backups, function ($left, $right) {
        return filemtime($right) <=> filemtime($left);
    });
    foreach ($backups as $backup) {
        $transactionId = substr($backup, strlen($filename . '.bak.'));
        $temporary = $filename . '.tmp.' . $transactionId;
        if ($transactionId === '' || !is_file($temporary)) {
            continue;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        if ($document->load($backup, LIBXML_NONET)) {
            $root = $document->documentElement;
            if ($root !== null && $root->tagName === 'answers'
                && $root->getAttribute('variant_id') === $variantId
                && $root->getAttribute('kind') === 'student') {
                if (!rename($backup, $filename)) {
                    throw new RuntimeException('Не удалось восстановить резервную копию ответов');
                }
                @unlink($temporary);
                return;
            }
        }
    }
}

function createAnswersDocument($variantId, $startedAt = null, $kimNumber = '') {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->formatOutput = true;
    $root = $document->appendChild($document->createElement('answers'));
    $root->setAttribute('variant_id', $variantId);
    $root->setAttribute('kind', 'student');
    $root->setAttribute('started_at', (string)($startedAt ?: time()));
    if ($kimNumber !== '') {
        $root->setAttribute('kim_number', $kimNumber);
    }
    return $document;
}

function archiveAnswersFile($filename, $label) {
    $suffix = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $archive = $filename . '.' . $label . '.' . $suffix;
    if (!rename($filename, $archive)) {
        throw new RuntimeException('Не удалось архивировать предыдущую попытку');
    }
    return $archive;
}
