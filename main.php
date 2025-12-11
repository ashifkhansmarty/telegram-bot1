<?php

// Telegram bot token
$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL   = "https://api.telegram.org/bot$botToken/";

// Read POST body from Telegram (Render fully supports php://input)
$input = file_get_contents("php://input");
$update = json_decode($input, true);

// If no update received
if(!$update) {
    echo "No update received";
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$text   = trim($update["message"]["text"]);

// 10-digit number check
if (preg_match('/^[0-9]{10}$/', $text)) {

    // Call your API
    $apiUrl = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    curl_close($ch);

    sendMessage($chatId, $apiResponse);

} else {
    sendMessage($chatId, "Send a valid 10-digit mobile number.");
}

// Send message to Telegram
function sendMessage($chatId, $msg)
{
    global $apiURL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "chat_id" => $chatId,
        "text" => $msg
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
