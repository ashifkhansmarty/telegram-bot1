<?php

$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL   = "https://api.telegram.org/bot$botToken/";

// Read Telegram update
$input = file_get_contents("php://input");
$update = json_decode($input, true);

if (!$update) {
    echo "No update received";
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$text   = trim($update["message"]["text"]);

if (preg_match('/^[0-9]{10}$/', $text)) {

    $url = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);

    sendMessage($chatId, $resp);

} else {
    sendMessage($chatId, "Send a valid 10-digit mobile number.");
}

function sendMessage($chatId, $message)
{
    global $apiURL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "chat_id" => $chatId,
        "text" => $message
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
