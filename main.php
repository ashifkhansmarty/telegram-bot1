<?php

$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL   = "https://api.telegram.org/bot$botToken/";
$adminID  = 1229178839;
$adminContact = "infoggz";

// Persistent storage files
$creditsFile = 'credits.json';
$usersFile   = 'users.json';

// Load existing data
$credits = file_exists($creditsFile) ? json_decode(file_get_contents($creditsFile), true) : [];
$users   = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

// Read incoming update
$input = file_get_contents("php://input");
$update = json_decode($input, true);
if (!$update) exit;

$chatId = $update["message"]["chat"]["id"];
$userId = $update["message"]["from"]["id"];
$text   = trim($update["message"]["text"]);

// Give 2 credits to new users
if (!isset($credits[$userId])) $credits[$userId] = 2;

// Track users without losing credits
$users[$userId] = [
    "username" => isset($update["message"]["from"]["username"]) ? $update["message"]["from"]["username"] : "N/A",
    "credits" => $credits[$userId]
];

saveData();

// Admin credit management via reply
if (isset($update["message"]["reply_to_message"]) && ($userId == $adminID)) {
    $replyUserId = $update["message"]["reply_to_message"]["from"]["id"];

    if (preg_match('/^\/give (\d+)$/', $text, $matches)) {
        $amt = intval($matches[1]);
        $credits[$replyUserId] = (isset($credits[$replyUserId]) ? $credits[$replyUserId] : 0) + $amt;
        $users[$replyUserId]['credits'] = $credits[$replyUserId];
        saveData();
        sendMessage($chatId, "✅ Added <b>$amt credits</b> to <code>$replyUserId</code>");
        exit;
    }

    if (preg_match('/^\/remove (\d+)$/', $text, $matches)) {
        $amt = intval($matches[1]);
        $credits[$replyUserId] = max(0, (isset($credits[$replyUserId]) ? $credits[$replyUserId] : 0) - $amt);
        $users[$replyUserId]['credits'] = $credits[$replyUserId];
        saveData();
        sendMessage($chatId, "❌ Removed <b>$amt credits</b> from <code>$replyUserId</code>");
        exit;
    }
}

// Commands
switch ($text) {
    case "/start":
        sendMessage($chatId, "🚀 <b>Welcome to Mobile Info Bot!</b>\n\n"
            . "Send any 10-digit number to scan.\n"
            . "💳 Credits: <b>{$credits[$userId]}</b>");
        break;

    case "/help":
        sendMessage($chatId, "📖 <b>Help - Mobile Info Bot</b>\n\n"
            . "Send a 10-digit number to retrieve details.\n"
            . "Admin Commands:\n"
            . " - Reply with /give <amount> or /remove <amount> to manage credits.\n"
            . " - /users : List all users.\n"
            . " - /credit : Check your credits.");
        break;

    case "/credit":
        sendMessage($chatId, "💳 You have <b>{$credits[$userId]}</b> credits remaining.");
        break;

    case "/users":
        if ($userId != $adminID) { sendMessage($chatId, "🚫 Admin only."); exit; }
        $msg = "👥 <b>Registered Users</b>\n\n";
        $buttons = [];
        foreach ($users as $uid => $u) {
            $msg .= "🆔 <b>ID:</b> <code>$uid</code>\n";
            $msg .= "👤 <b>Username:</b> @" . $u['username'] . "\n";
            $msg .= "💳 <b>Credits:</b> " . $u['credits'] . "\n";
            $msg .= "────────────────────\n";
            $buttons[] = [["text"=>"Copy ID $uid","switch_inline_query_current_chat"=>"$uid"]];
        }
        sendMessage($chatId, $msg, $buttons);
        break;

    default:
        if (preg_match('/^[0-9]{10}$/', $text)) {

            if ($credits[$userId] < 1) {
                sendMessage($chatId, "⚠️ You have 0 credits left.\nContact admin @$adminContact.");
                exit;
            }

            $credits[$userId] -= 1;
            $users[$userId]['credits'] = $credits[$userId];
            saveData();

            $url = "https://mynkapi.amit1100941.workers.dev/api?key=mynk01&type=mobile&term=$text";
            $resp = file_get_contents($url);
            $data = json_decode($resp, true);

            if (isset($data['success']) && $data['success'] === true) {

                // If no results
                if (isset($data['result']['message'])) {
                    sendMessage($chatId, "⚠️ <b>No data found!</b>\nPlease check the number or contact admin @$adminContact.");
                } else {
                    $formatted = "🚀 <b>📊 Mobile Scan Report</b> 🚀\n\n";
                    $formatted .= "📱 <b>Mobile:</b> <code>$text</code>\n\n";

                    foreach ($data['result'] as $person) {
                        $formatted .= "👤 <b>Name:</b> " . htmlspecialchars($person['name']) . "\n";
                        $formatted .= "🖊 <b>Father:</b> " . htmlspecialchars($person['father_name']) . "\n";
                        $formatted .= "🌍 <b>Address:</b> " . htmlspecialchars($person['address']) . "\n";
                        $formatted .= "📞 <b>Alt Mobile:</b> " . (!empty($person['alt_mobile']) ? "<code>" . htmlspecialchars($person['alt_mobile']) . "</code>" : "N/A") . "\n";
                        $formatted .= "📡 <b>Circle:</b> " . htmlspecialchars($person['circle']) . "\n";
                        $formatted .= "🆔 <b>ID Number:</b> <code>" . htmlspecialchars($person['id_number']) . "</code>\n";
                        $formatted .= "📧 <b>Email:</b> " . (!empty($person['email']) ? htmlspecialchars($person['email']) : "N/A") . "\n";
                        $formatted .= "────────────────────────\n";

                        // Inline buttons for copy
                        $buttons = [
                            [
                                ["text"=>"Copy Mobile","switch_inline_query_current_chat"=>$text],
                                ["text"=>"Copy Alt Mobile","switch_inline_query_current_chat"=>$person['alt_mobile']]
                            ],
                            [
                                ["text"=>"Copy ID","switch_inline_query_current_chat"=>$person['id_number']],
                                ["text"=>"Copy Address","switch_inline_query_current_chat"=>$person['address']],
                                ["text"=>"Copy Email","switch_inline_query_current_chat"=>$person['email']]
                            ]
                        ];

                        sendMessage($chatId, $formatted, $buttons);
                    }
                }

            } else {
                sendMessage($chatId, "⚠️ API call failed!");
            }

        } else {
            sendMessage($chatId, "⚠️ Invalid input.\nSend a valid 10-digit mobile number.");
        }
        break;
}

// Save credits & users
function saveData() {
    global $credits, $users, $creditsFile, $usersFile;
    file_put_contents($creditsFile, json_encode($credits));
    file_put_contents($usersFile, json_encode($users));
}

// Send Telegram message
function sendMessage($chatId, $msg, $buttons = null) {
    global $apiURL;
    $data = ["chat_id" => $chatId, "text" => $msg, "parse_mode" => "HTML"];
    if ($buttons) $data["reply_markup"] = json_encode(["inline_keyboard" => $buttons]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

?>
