<?php
// webhook.php - main webhook endpoint
require 'db.php';
require 'functions.php';

// Read incoming update
$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(400); exit; }
$update = json_decode($raw, true);

// Helpers
$token = getenv('TELEGRAM_TOKEN');
if (!$token) { error_log("Missing TELEGRAM_TOKEN env"); http_response_code(500); exit; }

$chat_id = null;
$text = null;
$tg_user = null;
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    $tg_user = $update['message']['from'];
} elseif (isset($update['callback_query'])) {
    $chat_id = $update['callback_query']['from']['id'];
    $text = '/callback';
    $callback_query = $update['callback_query'];
    $tg_user = $callback_query['from'];
}

// Basic registration on /start
if ($text === '/start' || (isset($update['message']['chat']['type']) && $text === '/start')) {
    // handle referral code if provided
    $parts = explode(' ', $text);
    $ref = null;
    // /start REFERRALCODE (Bot sends start with param when using t.me/Bot?start=CODE)
    if (isset($update['message']['text']) && preg_match('/\/start (.+)/', $update['message']['text'], $m)) {
        $ref = $m[1];
    } elseif (isset($update['message']['entities'])) {
        // nothing special
    }

    $existingUser = getUserByTgId($conn, $tg_user['id']);
    if (!$existingUser) {
        createUser($conn, $tg_user['id'], $tg_user['username'] ?? '', $tg_user['first_name'] ?? '', $ref);
        sendMessage($chat_id, "Welcome! Your account has been created. Use /menu to open dashboard.");
    } else {
        sendMessage($chat_id, "Welcome back! Use /menu to open dashboard.");
    }
    exit;
}

// MENU
if ($text === '/menu' || $text === 'menu') {
    $user = getUserByTgId($conn, $tg_user['id']);
    if (!$user) { sendMessage($chat_id, "Please /start first."); exit; }
    $menu = [
        'keyboard' => [
            [['text'=>'🔎 Tasks'], ['text'=>'💰 Balance']],
            [['text'=>'🔗 Refer'], ['text'=>'📤 Withdraw']],
            [['text'=>'👤 My Profile'], ['text'=>'📝 Help']]
        ],
        'resize_keyboard' => true
    ];
    sendMessage($chat_id, "Main Menu — choose:", $menu);
    exit;
}

// Show balance
if ($text === '💰 Balance' || $text === '/balance') {
    $user = getUserByTgId($conn, $tg_user['id']);
    if (!$user) { sendMessage($chat_id, "Please /start first."); exit; }
    sendMessage($chat_id, "Your balance: $" . number_format($user['balance'], 2));
    exit;
}

// Show tasks
if ($text === '🔎 Tasks' || $text === '/tasks') {
    // fetch active tasks
    $res = $conn->query("SELECT * FROM tasks WHERE active=1 ORDER BY id DESC LIMIT 20");
    $msg = "Available Tasks:\n\n";
    while ($row = $res->fetch_assoc()) {
        $msg .= "ID: {$row['id']} | {$row['title']} — Reward: ${$row['reward']}\n";
        $msg .= "👉 /task_{$row['id']}\n\n";
    }
    sendMessage($chat_id, $msg);
    exit;
}

// Request a specific task
if (preg_match('/\/task_(\d+)/', $text, $m)) {
    $task_id = (int)$m[1];
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    if (!$task) { sendMessage($chat_id, "Task not found."); exit; }

    $msg = "Task: {$task['title']}\nReward: \${$task['reward']}\n\nFollow the link and then send proof (link or screenshot) using /complete_{$task_id} PROOF";
    sendMessage($chat_id, $msg);
    exit;
}

// Complete task
if (preg_match('/\/complete_(\d+)\s+(.+)/', $text, $m)) {
    $task_id = (int)$m[1];
    $proof = trim($m[2]);
    $user = getUserByTgId($conn, $tg_user['id']);
    if (!$user) { sendMessage($chat_id, "Please /start first."); exit; }

    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    if (!$task) { sendMessage($chat_id, "Task not found."); exit; }

    // insert completion as pending
    $stmt = $conn->prepare("INSERT INTO completions (user_id, task_id, proof, reward, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iids", $user['id'], $task_id, $proof, $task['reward']);
    $stmt->execute();
    sendMessage($chat_id, "Task submitted for review. Admin will check and approve soon.");
    // Optionally notify admin group - if you set ADMIN_CHAT_ID env
    $admin_chat = getenv('ADMIN_CHAT_ID');
    if ($admin_chat) {
        sendMessage($admin_chat, "New completion: user {$user['username']} (id: {$user['id']}) Task: {$task['title']} Proof: {$proof}\nApprove with /approve_{$conn->insert_id} or reject with /reject_{$conn->insert_id}");
    }
    exit;
}

// Withdraw request
if ($text === '📤 Withdraw' || preg_match('/\/withdraw\s+(.+)/', $text, $m)) {
    $user = getUserByTgId($conn, $tg_user['id']);
    if (!$user) { sendMessage($chat_id, "Please /start first."); exit; }

    // If user sends /withdraw AMOUNT|METHOD|ACCOUNT
    if (preg_match('/\/withdraw\s+([0-9]+(?:\.[0-9]{1,2})?)\|(.+)\|(.+)/', $text, $mm)) {
        $amount = (float)$mm[1]; $method = trim($mm[2]); $account = trim($mm[3]);
        if ($amount <= 0 || $amount > $user['balance']) { sendMessage($chat_id, "Invalid amount."); exit; }
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, method, account_info, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("idss", $user['id'], $amount, $method, $account);
        $stmt->execute();
        // deduct balance immediately or upon approval (choice). Here we keep until admin approves.
        sendMessage($chat_id, "Withdrawal request submitted. Admin will review.");
        $admin_chat = getenv('ADMIN_CHAT_ID');
        if ($admin_chat) {
            sendMessage($admin_chat, "New withdrawal: user {$user['username']} id {$user['id']} amount \${$amount} method {$method} account {$account}. Approve with /approve_withdraw_{$conn->insert_id}");
        }
        exit;
    } else {
        $msg = "To withdraw, send message in this format:\n/withdraw AMOUNT|METHOD|ACCOUNT\nExample: /withdraw 5.00|bKash|017XXXXXXXX";
        sendMessage($chat_id, $msg);
        exit;
    }
}

// Admin commands: approve completion, reject, approve withdraw
if (preg_match('/\/approve_(\d+)/', $text, $m) || preg_match('/\/reject_(\d+)/', $text, $m)) {
    // Only allow ADMIN_CHAT_ID or admin list
    $admin_chat = getenv('ADMIN_CHAT_ID');
    if (!$admin_chat || $chat_id != $admin_chat) { sendMessage($chat_id, "You are not admin."); exit; }
    if (preg_match('/\/approve_(\d+)/', $text, $a)) {
        $comp_id = (int)$a[1];
        // approve completion: set status approved and credit user
        $stmt = $conn->prepare("SELECT * FROM completions WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $comp_id);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        if (!$comp) { sendMessage($chat_id, "Completion not found."); exit; }
        $stmt = $conn->prepare("UPDATE completions SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $comp_id);
        $stmt->execute();
        creditUser($conn, $comp['user_id'], $comp['reward']);
        sendMessage($chat_id, "Completion approved and user credited.");
        // notify user
        $u = $conn->query("SELECT tg_id FROM users WHERE id={$comp['user_id']}")->fetch_assoc();
        if ($u) sendMessage($u['tg_id'], "Your task was approved. +{$comp['reward']} credited.");
        exit;
    } elseif (preg_match('/\/reject_(\d+)/', $text, $r)) {
        $comp_id = (int)$r[1];
        $stmt = $conn->prepare("UPDATE completions SET status='rejected' WHERE id=?");
        $stmt->bind_param("i", $comp_id);
        $stmt->execute();
        sendMessage($chat_id, "Completion rejected.");
        exit;
    }
}

// Admin approve withdraw: /approve_withdraw_123
if (preg_match('/\/approve_withdraw_(\d+)/', $text, $m)) {
    $admin_chat = getenv('ADMIN_CHAT_ID');
    if (!$admin_chat || $chat_id != $admin_chat) { sendMessage($chat_id, "You are not admin."); exit; }
    $wid = (int)$m[1];
    $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $wid);
    $stmt->execute();
    $w = $stmt->get_result()->fetch_assoc();
    if (!$w) { sendMessage($chat_id, "Withdraw not found."); exit; }
    // deduct user balance and mark paid (manual payment by admin)
    $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id=?");
    $stmt->bind_param("di", $w['amount'], $w['user_id']);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE withdrawals SET status='paid' WHERE id=?");
    $stmt->bind_param("i", $wid);
    $stmt->execute();
    sendMessage($chat_id, "Withdraw approved and marked as paid. (Make manual payment to the user.)");
    $u = $conn->query("SELECT tg_id FROM users WHERE id={$w['user_id']}")->fetch_assoc();
    if ($u) sendMessage($u['tg_id'], "Your withdrawal \${$w['amount']} has been approved and paid. Check your account.");
    exit;
}

// default fallback
sendMessage($chat_id, "Unknown command. Use /menu to open the main menu.");
