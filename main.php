<?php

$botToken = "7639044509:AAH8-Uh024ffsU6E2jq9kVi2QFwJfPAARrI";
$apiURL = "https://api.telegram.org/bot$botToken/";
$adminID = 1229178839;
$adminContact = "infoggz";

// Storage files
$creditsFile = 'credits.json';
$usersFile = 'users.json';

// Load data
$credits = file_exists($creditsFile) ? json_decode(file_get_contents($creditsFile), true) : [];
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

// Read incoming update
$input = file_get_contents("php://input");
$update = json_decode($input, true);
if (!$update) exit;

$chatId = $update["message"]["chat"]["id"];
$userId = $update["message"]["from"]["id"];
$text = trim($update["message"]["text"]);

// Give 10 credits to new users
if (!isset($credits[$userId])) $credits[$userId] = 10;

// Track users
$users[$userId] = [
    "username" => $update["message"]["from"]["username"] ?? "N/A",
    "credits" => $credits[$userId]
];

saveData();

// Admin credit commands
if ($userId == $adminID) {
    if (preg_match('/^\/givecredit (\d+) (\d+)$/', $text, $matches)) {
        $targetID = intval($matches[1]);
        $amount = intval($matches[2]);
        $credits[$targetID] = ($credits[$targetID] ?? 0) + $amount;
        $users[$targetID]['credits'] = $credits[$targetID];
        saveData();
        sendMessage($chatId, "✅ Added <b>$amount credits</b> to <code>$targetID</code>");
        exit;
    }
    if (preg_match('/^\/removecredit (\d+) (\d+)$/', $text, $matches)) {
        $targetID = intval($matches[1]);
        $amount = intval($matches[2]);
        $credits[$targetID] = max(0, ($credits[$targetID] ?? 0) - $amount);
        $users[$targetID]['credits'] = $credits[$targetID];
        saveData();
        sendMessage($chatId, "❌ Removed <b>$amount credits</b> from <code>$targetID</code>");
        exit;
    }
}

// Commands
switch ($text) {
    case "/start":
        sendMessage($chatId, "🟢 <b>Welcome to Digital Matrix Mobile Scan</b>\nSend any 10-digit number to scan.");
        break;

    case "/help":
        sendMessage($chatId, "📖 <b>Help</b>\nSend a 10-digit number to get details.\nAdmin commands:\n/givecredit <UserID> <amount>\n/removecredit <UserID> <amount>\n/users");
        break;

    case "/users":
        if ($userId != $adminID) { sendMessage($chatId, "🚫 Admin only."); exit; }
        $msg = "👥 <b>Registered Users</b>\n\n";
        foreach ($users as $uid => $u) {
            $msg .= "🆔 <b>ID:</b> <code>$uid</code>\n";
            $msg .= "👤 <b>Username:</b> @" . $u['username'] . "\n";
            $msg .= "────────────────────\n";
        }
        sendMessage($chatId, $msg);
        break;

    default:
        if (preg_match('/^[0-9]{10}$/', $text)) {
            if ($credits[$userId] < 1) {
                sendMessage($chatId, "⚠️ You have 0 credits left. Contact admin @$adminContact.");
                exit;
            }

            $credits[$userId] -= 1;
            $users[$userId]['credits'] = $credits[$userId];
            saveData();

            $url = "https://mynkapi.amit1100941.workers.dev/api?key=paidkey&type=mobile&term="http";
            $resp = file_get_contents($url);
            $data = json_decode($resp, true);

            if (!isset($data['success']) || !$data['success']) {
                sendMessage($chatId, "⚠️ API call failed!");
                exit;
            }

            if (isset($data['result']['message'])) {
                sendMessage($chatId, "⚠️ <b>No data found!</b>\nContact admin @$adminContact.");
                exit;
            }

            foreach ($data['result'] as $person) {
                $formatted = "🟢━━━━━━━━━━━━━━🟢\n";
                $formatted .= "💾 <b>DIGITAL MATRIX REPORT</b> 💾\n";
                $formatted .= "🟢━━━━━━━━━━━━━━🟢\n\n";
                $formatted .= "📱 <b>Mobile:</b> <code>$text</code>\n";
                $formatted .= "📞 <b>Alt Mobile:</b> <code>" . htmlspecialchars($person['alt_mobile']) . "</code>\n";
                $formatted .= "🖊 <b>Name:</b> " . htmlspecialchars($person['name']) . "\n";
                $formatted .= "👤 <b>Father:</b> " . htmlspecialchars($person['father_name']) . "\n";
                $formatted .= "🌍 <b>Address:</b> <code>" . htmlspecialchars($person['address']) . "</code>\n";
                $formatted .= "🆔 <b>ID Number:</b> <code>" . htmlspecialchars($person['id_number']) . "</code>\n";
                $formatted .= "📧 <b>Email:</b> <code>" . htmlspecialchars($person['email']) . "</code>\n";
                $formatted .= "📡 <b>Circle:</b> " . htmlspecialchars($person['circle']) . "\n";

                sendMessage($chatId, $formatted);
            }

        } else {
            sendMessage($chatId, "⚠️ Invalid input. Send a valid 10-digit mobile number.");
        }
        break;
}

// Save data
function saveData() {
    global $credits, $users, $creditsFile, $usersFile;
    file_put_contents($creditsFile, json_encode($credits));
    file_put_contents($usersFile, json_encode($users));
}

// Send message
function sendMessage($chatId, $msg) {
    global $apiURL;
    $data = ["chat_id" => $chatId, "text" => $msg, "parse_mode" => "HTML"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL."sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

?>
