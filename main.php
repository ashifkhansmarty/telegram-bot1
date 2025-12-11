<?php

$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL   = "https://api.telegram.org/bot$botToken/";
$adminID  = 1229178839; // Admin ID
$adminContact = "infoggz";

$creditsFile = 'credits.json';
if(!file_exists($creditsFile)) file_put_contents($creditsFile, json_encode([]));

// Read Telegram update
$input = file_get_contents("php://input");
$update = json_decode($input, true);
if(!$update) exit;

// ---------- HANDLE CALLBACK QUERIES (INLINE BUTTONS) ----------
if(isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $fromId = $callback['from']['id'];
    $chatIdCb = $callback['message']['chat']['id'];

    $credits = json_decode(file_get_contents($creditsFile), true);
    if($data === "check_credits") {
        $credit = isset($credits[$fromId]) ? $credits[$fromId] : 0;
        sendMessage($chatIdCb, "👽 Your current intergalactic credits: $credit 🛸");
    }
    exit;
}

// ---------- MESSAGE HANDLING ----------
$chatId = $update["message"]["chat"]["id"];
$userId = $update["message"]["from"]["id"];
$text   = trim($update["message"]["text"]);

// Load credits
$credits = json_decode(file_get_contents($creditsFile), true);

// ---------- GIVE NEW USERS 2 CREDITS ----------
if(!isset($credits[$userId])) {
    $credits[$userId] = 2; // Free 2 credits
    file_put_contents($creditsFile, json_encode($credits));
}

// ---------- ADMIN REPLY-TO USER CREDITS ----------
if(isset($update["message"]["reply_to_message"]) && ($userId == $adminID)) {
    $replyUserId = $update["message"]["reply_to_message"]["from"]["id"];

    // Give credits by replying
    if(preg_match('/^\/give (\d+)$/', $text, $matches)) {
        $amt = $matches[1];
        $credits[$replyUserId] = isset($credits[$replyUserId]) ? $credits[$replyUserId] + $amt : $amt;
        file_put_contents($creditsFile, json_encode($credits));
        sendMessage($chatId, "🛸 Added $amt credits to user $replyUserId 👽");
        exit;
    }

    // Remove credits by replying
    if(preg_match('/^\/remove (\d+)$/', $text, $matches)) {
        $amt = $matches[1];
        $credits[$replyUserId] = max(0, (isset($credits[$replyUserId]) ? $credits[$replyUserId] - $amt : 0));
        file_put_contents($creditsFile, json_encode($credits));
        sendMessage($chatId, "🛸 Removed $amt credits from user $replyUserId 👽");
        exit;
    }
}

// ---------- COMMANDS ----------
if ($text === "/start") {
    $buttons = [
        [["text" => "🛸 Check Credits", "callback_data" => "check_credits"]]
    ];
    sendMessage($chatId, "👽 Greetings, Earthling! You have been granted 2 free intergalactic credits 🛸\n\nSend a 10-digit mobile number to scan.", $buttons);
} elseif ($text === "/help") {
    sendMessage($chatId, "🛸 Alien Bot Help 🛸\n\nSend a 10-digit mobile number to scan.\nCheck your credits with /credit.\nAdmins can give/remove credits with /givecredit and /removecredit.\nView all users with /users.\nAdmin can reply to a user to /give or /remove credits.");
} elseif ($text === "/credit") {
    $credit = isset($credits[$userId]) ? $credits[$userId] : 0;
    sendMessage($chatId, "👽 Your current intergalactic credits: $credit 🛸");
}
// ---------- ADMIN COMMANDS ----------
elseif (preg_match('/^\/givecredit (\d+) (\d+)$/', $text, $matches)) {
    if($userId != $adminID) { sendMessage($chatId, "🚫 Only the admin can give credits!"); exit; }
    $uid = $matches[1]; $amt = $matches[2];
    $credits[$uid] = isset($credits[$uid]) ? $credits[$uid] + $amt : $amt;
    file_put_contents($creditsFile, json_encode($credits));
    sendMessage($chatId, "🛸 Added $amt credits to user $uid 👽");
} elseif (preg_match('/^\/removecredit (\d+) (\d+)$/', $text, $matches)) {
    if($userId != $adminID) { sendMessage($chatId, "🚫 Only the admin can remove credits!"); exit; }
    $uid = $matches[1]; $amt = $matches[2];
    $credits[$uid] = max(0, (isset($credits[$uid]) ? $credits[$uid] - $amt : 0));
    file_put_contents($creditsFile, json_encode($credits));
    sendMessage($chatId, "🛸 Removed $amt credits from user $uid 👽");
}
// ---------- ADMIN COMMAND: SHOW USERS ----------
elseif ($text === "/users") {
    if($userId != $adminID) { 
        sendMessage($chatId, "🚫 Only the admin can view the user list!"); 
        exit; 
    }

    if(empty($credits)) {
        sendMessage($chatId, "👽 No users have registered yet.");
        exit;
    }

    $msg = "🛸 Registered Users & Credits 👽\n\n";
    foreach($credits as $uid => $credit) {
        $msg .= "👤 User ID: $uid | Credits: $credit\n";
    }

    sendMessage($chatId, $msg);
}
// ---------- MOBILE SCAN ----------
elseif (preg_match('/^[0-9]{10}$/', $text)) {

    $credit = isset($credits[$userId]) ? $credits[$userId] : 0;
    if($credit < 1) { 
        $buttons = [
            [["text" => "📩 Contact Admin", "url" => "https://t.me/$adminContact"]]
        ];
        sendMessage($chatId, "🚫 You have 0 credits! Click below to contact the admin to get more intergalactic credits.", $buttons);
        exit; 
    }

    // Deduct 1 credit per scan
    $credits[$userId] -= 1;
    file_put_contents($creditsFile, json_encode($credits));

    $url = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true);

    if (isset($data['success']) && $data['success'] === true) {
        $formatted = "👽🛸 ~*~ Intergalactic Scan Report ~*~ 🛸👽\n\n";
        foreach ($data['result'] as $person) {
            $formatted .= "🌠 Name        : " . $person['name'] . "\n";
            $formatted .= "🛸 Father      : " . $person['father_name'] . "\n";
            $formatted .= "📡 Mobile      : " . $person['mobile'] . "\n";
            $formatted .= "📡 Alt Mobile  : " . $person['alt_mobile'] . "\n";
            $formatted .= "🏠 Address     : " . $person['address'] . "\n";
            $formatted .= "🌌 Circle     : " . $person['circle'] . "\n";
            $formatted .= "🆔 ID Number  : " . $person['id_number'] . "\n";
            if(!empty($person['email'])) { $formatted .= "✉️ Email       : " . $person['email'] . "\n"; }
            $formatted .= "☄️------------------------☄️\n";
        }
        $formatted .= "👽 Remaining Credits: " . $credits[$userId] . " 🛸";
        sendMessage($chatId, $formatted);
    } else {
        sendMessage($chatId, "👽 Alert! No intergalactic data found for this number.");
    }

} else {
    sendMessage($chatId, "🛸 Invalid input! Send a 10-digit mobile number to start the scan.");
}

// ---------- SEND MESSAGE FUNCTION ----------
function sendMessage($chatId, $msg, $buttons = null)
{
    global $apiURL;
    $data = [
        "chat_id" => $chatId,
        "text" => $msg,
        "parse_mode" => "HTML"
    ];

    if($buttons) {
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
