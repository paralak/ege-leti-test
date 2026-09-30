<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    // Путь к XML-файлу
    $xmlFile = __DIR__ . '/variant/answers.xml';

    // Создаём новый XML или загружаем существующий
    if (file_exists($xmlFile) && filesize($xmlFile) > 0) {
        $xml = simplexml_load_file($xmlFile);
        if ($xml === false) {
            // Если файл повреждён, создаём новый XML
            $xml = new SimpleXMLElement('<answers></answers>');
        }
    } else {
        // Если файла нет или он пуст, создаём новый XML
        $xml = new SimpleXMLElement('<answers></answers>');
    }

    if ($action === 'load') {
        // Загрузка ответа
        $number = (int) $_POST['number'];
        // $xmlFile = 'variant/answers.xml';

        if (file_exists($xmlFile) && filesize($xmlFile) > 0) {
            // $xml = simplexml_load_file($xmlFile);
            if ($xml !== false) {
                foreach ($xml->answer as $answer) {
                    if ((int) $answer->number == $number) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'success',
                            'answer' => (string) $answer->value
                        ]);
                        exit;
                    }
                }
            }
        }

        // Ответ не найден
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'answer' => '']);
        exit;
    }

    // Сохранение ответа (существующий код)
    // Подготовка данных
    $answerData = [
        'taskID' => htmlspecialchars($_POST['taskID'], ENT_XML1),
        'number' => (int) $_POST['number'],
        'answer' => htmlspecialchars($_POST['answer'], ENT_XML1),
    ];

    // Удаляем старую запись с таким же номером, если она существует
    foreach ($xml->answer as $answer) {
        if ((int) $answer->number == $answerData['number']) {
            $dom = dom_import_simplexml($answer);
            $dom->parentNode->removeChild($dom);
            break;
        }
    }

    // Добавляем новую запись
    $newAnswer = $xml->addChild('answer');
    $newAnswer->addChild('taskID', $answerData['taskID']);
    $newAnswer->addChild('number', $answerData['number']);
    $newAnswer->addChild('value', $answerData['answer']);

    // Сохраняем XML
    if ($xml->asXML($xmlFile) === false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Не удалось сохранить ответы']);
        exit;
    }

    // Отправляем ответ об успехе
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Answer saved successfully']);
} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>
