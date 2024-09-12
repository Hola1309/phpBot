<?php

define('TELEGRAM_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/');

$host = 'localhost';
$db = '';
$user = '';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die('Ошибка подключения (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

function sendMessage($chat_id, $text) {
    $url = API_URL . 'sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
    ];
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    curl_exec($ch);
    curl_close($ch);
}

function getUpdates($offset) {
    $url = API_URL . 'getUpdates?offset=' . $offset;
    $response = file_get_contents($url);
    return json_decode($response, true);
}

function initializeUser($chat_id, $mysqli) {
    $stmt = $mysqli->prepare("INSERT INTO users (chat_id) VALUES (?)");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $stmt->close();
}

function getBalance($chat_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT balance FROM users WHERE chat_id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
    return $balance;
}

function updateBalance($chat_id, $amount, $mysqli) {
    $stmt = $mysqli->prepare("UPDATE users SET balance = balance + ? WHERE chat_id = ?");
    $stmt->bind_param("di", $amount, $chat_id);
    $stmt->execute();
    $stmt->close();
}

$offset = 0;

while (true) {
    $updates = getUpdates($offset);
    foreach ($updates['result'] as $update) {
        $chat_id = $update['message']['chat']['id'];
        $text = $update['message']['text'];

        if ($mysqli->query("SELECT * FROM users WHERE chat_id = $chat_id")->num_rows == 0) {
            initializeUser($chat_id, $mysqli);
            sendMessage($chat_id, "Ваш аккаунт создан. Начальный баланс: $0.00");
        }

        if (is_numeric(str_replace([','], ['.'], $text))) {
            $amount = floatval(str_replace([','], ['.'], $text));

            $current_balance = getBalance($chat_id, $mysqli);

            if ($amount < 0 && abs($amount) > $current_balance) {
                sendMessage($chat_id, "Ошибка: недостаточно средств на счете.");
            } else {
                updateBalance($chat_id, $amount, $mysqli);
                $new_balance = getBalance($chat_id, $mysqli);
                sendMessage($chat_id, "Ваш новый баланс: " . number_format($new_balance, 2, '.', '') . " $");
            }
        } else {
            sendMessage($chat_id, "Пожалуйста, введите число для изменения баланса.");
        }

        $offset = $update['update_id'] + 1;
    }
    sleep(1);
}

$mysqli->close();
?>