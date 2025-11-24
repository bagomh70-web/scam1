<?php
// functions.php - helpers for bot actions

function sendMessage($chat_id, $text, $reply_markup = null) {
    $token = getenv('TELEGRAM_TOKEN');
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function answerCallback($callback_id, $text='') {
    $token = getenv('TELEGRAM_TOKEN');
    $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $data = ['callback_query_id' => $callback_id, 'text' => $text, 'show_alert' => false];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

function getUserByTgId($conn, $tg_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE tg_id = ? LIMIT 1");
    $stmt->bind_param("s", $tg_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function createUser($conn, $tg_id, $username, $first_name, $ref=null) {
    // create referral code
    $code = bin2hex(random_bytes(4));
    $stmt = $conn->prepare("INSERT INTO users (tg_id, username, first_name, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $tg_id, $username, $first_name, $code, $ref);
    $stmt->execute();
    return $conn->insert_id;
}

// credit user balance
function creditUser($conn, $user_id, $amount) {
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $user_id);
    return $stmt->execute();
}
