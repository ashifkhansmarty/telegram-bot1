<?php

$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL   = "https://api.telegram.org/bot$botToken/";
$adminID  = 1229178839; // Admin ID
$adminContact = "infoggz";

$creditsFile = 'credits.json';
if (!file_exists($creditsFile)) file_put_contents($creditsFile, json_encode([]));

// Read update
$input = file_get_contents("php://input");
$update = json_decode($input, true);
if (!$update) exit;

// Handle callback queries for inline buttons
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $fromId = $callback['from']['id'];
    $chatIdCb = $callback['message']['chat']['id'];

    $credits = json_decode(file_get_contents($creditsFile), true);
    if ($data === "check_credits") {
        $credit = isset($credits[$fromId]) ? $credits[$fromId] : 0;
        sendMessage($chatIdCb, "👽 Your current credits: $credit");
    }
    exit;
}

// Message variables
$chatId = $update["message"]["chat"]["id"];
$userId = $update["message"]["from"]["id"];
$text   = trim($update["message"]["text"]);

$credits = json_decode(file_get_contents($creditsFile), true);

// Give 2 credits to new users
if (!isset($credits[$userId])) {
    $credits[$userId] = 2;
    file_put_contents($creditsFile, json_encode($credits));
}

// Admin reply-to message for giving/removing credits
if (isset($update["message"]["reply_to_message"]) && ($userId == $adminID)) {
    $replyUserId = $update["message"]["reply_to_message"]["from"]["id"];

    if (preg_match('/^\/give (\d+)$/', $text, $matches)) {
        $amt = intval($matches[1]);
        $credits[$replyUserId] = (isset($credits[$replyUserId]) ? $credits[$replyUserId] : 0) + $amt;
        file_put_contents($creditsFile, json_encode($credits));
        sendMessage($chatId, "🛸 Added $amt credits to user $replyUserId");
        exit;
    }

    if (preg_match('/^\/remove (\d+)$/', $text, $matches)) {
        $amt = intval($matches[1]);
        $credits[$replyUserId] = max(0, (isset($credits[$replyUserId]) ? $credits[$replyUserId] : 0) - $amt);
        file_put_contents($creditsFile, json_encode($credits));
        sendMessage($chatId, "🛸 Removed $amt credits from user $replyUserId");
        exit;
    }
}

// Commands
if ($text === "/start") {
    $buttons = [
        [["text" => "👽 Check Credits", "callback_data" => "check_credits"]]
    ];
    sendMessage($chatId, "👽 Welcome, Earthling! You have been granted 2 free credits.\nSend a 10-digit mobile number to scan.", $buttons);
} elseif ($text === "/help") {
    sendMessage($chatId, "👽 Help:\n- Send a 10-digit mobile number to scan.\n- Check credits with /credit\n- Admin only: /givecredit <user_id> <amount>, /removecredit <user_id> <amount>, /users\n- Admin reply to user with /give <amount> or /remove <amount> to adjust credits.");
} elseif ($text === "/credit") {
    $credit = isset($credits[$userId]) ? $credits[$userId] : 0;
    sendMessage($chatId, "👽 Your current credits: $credit");
} elseif (preg_match('/^\/givecredit (\d+) (\d+)$/', $text, $matches)) {
    if ($userId != $adminID) {
        sendMessage($chatId, "🚫 Only admin can give credits.");
        exit;
    }
    $uid = intval($matches[1]);
    $amt = intval($matches[2]);
    $credits[$uid] = (isset($credits[$uid]) ? $credits[$uid] : 0) + $amt;
    file_put_contents($creditsFile, json_encode($credits));
    sendMessage($chatId, "🛸 Added $amt credits to user $uid");
} elseif (preg_match('/^\/removecredit (\d+) (\d+)$/', $text, $matches)) {
    if ($userId != $adminID) {
        sendMessage($chatId, "🚫 Only admin can remove credits.");
        exit;
    }
    $uid = intval($matches[1]);
    $amt = intval($matches[2]);
    $credits[$uid] = max(0, (isset($credits[$uid]) ? $credits[$uid] : 0) - $amt);
    file_put_contents($creditsFile, json_encode($credits));
    sendMessage($chatId, "🛸 Removed $amt credits from user $uid");
} elseif ($text === "/users") {
    if ($userId != $adminID) {
        sendMessage($chatId, "🚫 Only admin can see users.");
        exit;
    }
    if (empty($credits)) {
        sendMessage($chatId, "👽 No users found.");
        exit;
    }
    $msg = "👽 Users & Credits:\n";
    foreach ($credits as $uid => $credit) {
        $msg .= "👤 User ID: $uid | Credits: $credit\n";
    }
    sendMessage($chatId, $msg);
}
// Scan mobile number
elseif (preg_match('/^[0-9]{10}$/', $text)) {
    $credit = isset($credits[$userId]) ? $credits[$userId] : 0;
    if ($credit < 1) {
        sendMessage($chatId, "❌ You have 0 credits left.\nPlease contact Admin @$adminContact to refill your credits.");
        exit;
    }

    // Deduct 1 credit
    $credits[$userId] -= 1;
    file_put_contents($creditsFile, json_encode($credits));

    // Call external API
    $url = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if (isset($data['success']) && $data['success'] === true) {
        $formatted = "👽 ALIEN SCAN REPORT 👽\n\n";
        $formatted .= "📱 Mobile: $text\n\n";

        foreach ($data['result'] as $person) {
            $formatted .= "🪸 Name: " . $person['name'] . "\n";
            $formatted .= "🖊 Father: " . $person['father_name'] . "\n\n";
            $formatted .= "🌍 Address:\n" . $person['address'] . "\n\n";
            $formatted .= "📞 Alt Mobile:\n" . (!empty($person['alt_mobile']) ? $person['alt_mobile'] : "N/A") . "\n";
            $formatted .= "📡 Circle: " . $person['circle'] . "\n";
            $formatted .= "🆔 ID Number: " . $person['id_number'] . "\n";
            $formatted .= "📧 Email: " . (!empty($person['email']) ? $person['email'] : "") . "\n";
            $formatted .= "--------------------\n";
        }

        $formatted .= "✨ By : GOV IND";
        sendMessage($chatId, $formatted);
    } else {
        sendMessage($chatId, "🚫 No data found for this number.");
    }
} else {
    sendMessage($chatId, "👽 Invalid input! Send a 10-digit mobile number to scan.");
}

function sendMessage($chatId, $msg, $buttons = null)
{
    global $apiURL;
    $data = [
        "chat_id" => $chatId,
        "text" => $msg,
        "parse_mode" => "HTML"
    ];

    if ($buttons) {
        $data["reply_markup"] = json_encode([
            "inline_keyboard" => $buttons
        ]);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

?>
