<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/answer_store.php';

$manifestFile = __DIR__ . '/variant/manifest.xml';
$manifest = is_file($manifestFile)
    ? simplexml_load_file($manifestFile, 'SimpleXMLElement', LIBXML_NONET)
    : false;
$variantId = $manifest !== false ? trim((string)$manifest->id) : '';
$variantTitle = $manifest !== false ? trim((string)$manifest->title) : '';
$error = '';

if ($variantId === '') {
    $error = 'Не удалось загрузить сведения о варианте. Обратитесь к техническому специалисту.';
} else {
    try {
        $hasActiveAttempt = withAnswersLock(function () use ($variantId) {
            $filename = __DIR__ . '/variant/answers.xml';
            recoverAnswersIfNeeded($filename, $variantId);
            if (!is_file($filename) || filesize($filename) === 0) {
                return false;
            }

            $document = new DOMDocument('1.0', 'UTF-8');
            if (!$document->load($filename, LIBXML_NONET)) {
                archiveAnswersFile($filename, 'invalid');
                return false;
            }

            $root = $document->documentElement;
            $isCurrentVariant = $root !== null
                && $root->tagName === 'answers'
                && $root->getAttribute('variant_id') === $variantId
                && $root->getAttribute('kind') === 'student';
            $startedAt = $isCurrentVariant ? (int)$root->getAttribute('started_at') : 0;
            $kimNumber = $isCurrentVariant ? $root->getAttribute('kim_number') : '';

            if (!$isCurrentVariant) {
                archiveAnswersFile($filename, 'previous');
                return false;
            }
            if (!preg_match('/^\d{1,32}$/D', $kimNumber) || $startedAt <= 0) {
                archiveAnswersFile($filename, 'unassigned');
                return false;
            }
            if (time() >= $startedAt + ANSWER_SESSION_RESET_SECONDS) {
                archiveAnswersFile($filename, 'expired');
                return false;
            }
            return true;
        });

        if ($hasActiveAttempt) {
            header('Location: index.php');
            exit;
        }
    } catch (Throwable $exception) {
        $error = 'Не удалось подготовить попытку. Проверьте права записи в папку variant.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $kimNumber = trim((string)($_POST['kim_number'] ?? ''));
    if (!preg_match('/^\d{1,32}$/D', $kimNumber)) {
        $error = 'Введите номер КИМ: от 1 до 32 цифр без пробелов.';
    } else {
        try {
            withAnswersLock(function () use ($variantId, $kimNumber) {
                $filename = __DIR__ . '/variant/answers.xml';
                if (is_file($filename)) {
                    archiveAnswersFile($filename, 'replaced');
                }
                atomicWriteAnswers($filename, createAnswersDocument($variantId, null, $kimNumber));
            });
            header('Location: index.php');
            exit;
        } catch (Throwable $exception) {
            $error = 'Не удалось начать тест. Проверьте права записи в папку variant.';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Начало тестирования</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Arial, sans-serif; color: #17284a; background: #e7edf9; }
        main { width: min(520px, 100%); padding: 34px; background: #fff; border: 1px solid #c5d1e5; border-radius: 14px; box-shadow: 0 8px 28px rgba(30, 50, 90, .16); }
        h1 { margin: 0 0 10px; font-size: 28px; }
        .variant { margin: 0 0 28px; color: #53627d; line-height: 1.45; }
        label { display: block; margin-bottom: 8px; font-weight: 700; }
        input { width: 100%; height: 48px; padding: 8px 12px; font-size: 20px; border: 1px solid #9bacC8; border-radius: 7px; }
        input:focus { outline: 3px solid rgba(232, 163, 23, .45); border-color: #1e325a; }
        button { width: 100%; min-height: 48px; margin-top: 18px; border: 0; border-radius: 7px; font-size: 17px; font-weight: 700; color: #fff; background: #1e325a; cursor: pointer; }
        button:hover { background: #304d85; }
        .hint { margin: 8px 0 0; font-size: 14px; color: #68758c; }
        .error { margin: 0 0 18px; padding: 12px; color: #842029; background: #f8d7da; border: 1px solid #f1aeb5; border-radius: 7px; }
    </style>
</head>
<body>
<main>
    <h1>Начало тестирования</h1>
    <p class="variant"><?= htmlspecialchars($variantTitle ?: 'Вариант ЕГЭ') ?></p>
    <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($variantId !== ''): ?>
        <form method="post" autocomplete="off">
            <label for="kim_number">Номер КИМ</label>
            <input id="kim_number" name="kim_number" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="32" required autofocus value="<?= htmlspecialchars((string)($_POST['kim_number'] ?? '')) ?>">
            <p class="hint">После нажатия кнопки начнётся отсчёт времени.</p>
            <button type="submit">Начать тест</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
