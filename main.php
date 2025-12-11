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

// Handle callback queries for inline buttons (only credit check)
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

// Give 2 credits to new users (hidden, no user message)
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
    sendMessage($chatId, "👽 Welcome, Alien Explorer!\n\nSend me a 10-digit mobile number to scan.\n\nYou have 2 free credits to start your mission.", $buttons);
} elseif ($text === "/help") {
    sendMessage($chatId, "👽 <b>Help - Alien Scan Bot</b>\n\n"
        . "📱 Send a 10-digit mobile number to retrieve scan reports.\n"
        . "⚡ Admin Commands:\n"
        . " - /givecredit &lt;user_id&gt; &lt;amount&gt;  (Add credits)\n"
        . " - /removecredit &lt;user_id&gt; &lt;amount&gt; (Remove credits)\n"
        . " - /users (List users and credits)\n"
        . "🛸 Reply to a user message with /give &lt;amount&gt; or /remove &lt;amount&gt; to modify credits.");
} elseif ($text === "/credit") {
    $credit = isset($credits[$userId]) ? $credits[$userId] : 0;
    sendMessage($chatId, "👽 You have <b>$credit</b> credits remaining.");
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
    $msg = "👽 <b>Users & Credits</b>:\n\n";
    foreach ($credits as $uid => $credit) {
        $msg .= "👤 <b>User ID:</b> $uid | <b>Credits:</b> $credit\n";
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

    // Deduct 1 credit silently
    $credits[$userId] -= 1;
    file_put_contents($creditsFile, json_encode($credits));

    // Call external API
    $url = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if (isset($data['success']) && $data['success'] === true) {
        if (is_array($data['result'])) {
            if (count($data['result']) === 0) {
                sendMessage($chatId, "🚫 No records found for this number.");
                exit;
            }
            $formatted = "👽 <b>ALIEN SCAN REPORT</b> 👽\n\n";
            $formatted .= "📱 <b>Mobile:</b> $text\n\n";

            foreach ($data['result'] as $person) {
                $formatted .= "🪸 <b>Name:</b> " . htmlspecialchars($person['name']) . "\n";
                $formatted .= "🖊 <b>Father:</b> " . htmlspecialchars($person['father_name']) . "\n\n";
                $formatted .= "🌍 <b>Address:</b>\n" . htmlspecialchars($person['address']) . "\n\n";
                $formatted .= "📞 <b>Alt Mobile:</b> " . (!empty($person['alt_mobile']) ? htmlspecialchars($person['alt_mobile']) : "N/A") . "\n";
                $formatted .= "📡 <b>Circle:</b> " . htmlspecialchars($person['circle']) . "\n";
                $formatted .= "🆔 <b>ID Number:</b> " . htmlspecialchars($person['id_number']) . "\n";
                $formatted .= "📧 <b>Email:</b> " . (!empty($person['email']) ? htmlspecialchars($person['email']) : "N/A") . "\n";
                $formatted .= "────────────────────────\n";
            }
            $formatted .= "✨ By : GOV IND";
            sendMessage($chatId, $formatted);

        } elseif (is_array($data['result']) === false && isset($data['result']['message'])) {
            sendMessage($chatId, "🚫 " . htmlspecialchars($data['result']['message']));
        } else {
            sendMessage($chatId, "🚫 No data found for this number.");
        }
    } else {
        sendMessage($chatId, "🚫 API call failed or no data.");
    }
} else {
    sendMessage($chatId, "👽 <b>Invalid input!</b>\nPlease send a valid 10-digit mobile number.");
}

function sendMessage($chatId, $msg, $buttons = null)
{
    global $apiURL;
    $data = [
        "chat_id" => $chatId,
        "text" => $msg,
        "parse_mode" => "HTML",
        "disable_web_page_preview" => true
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
