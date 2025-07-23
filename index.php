<?php
// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'Place_Your_Token_Here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');

// Initialize users.json if not exists
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, '{}');
    chmod(USERS_FILE, 0664);
}

// Initialize error.log if not exists
if (!file_exists(ERROR_LOG)) {
    file_put_contents(ERROR_LOG, "[" . date('Y-m-d H:i:s') . "] Log initialized\n");
    chmod(ERROR_LOG, 0664);
}

// Error logging
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG, "[$timestamp] $message\n", FILE_APPEND);
}

// Data management
function loadUsers() {
    try {
        if (!file_exists(USERS_FILE)) {
            file_put_contents(USERS_FILE, json_encode([]));
        }
        $data = file_get_contents(USERS_FILE);
        return json_decode($data, true) ?: [];
    } catch (Exception $e) {
        logError("Load users failed: " . $e->getMessage());
        return [];
    }
}

function saveUsers($users) {
    try {
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        return true;
    } catch (Exception $e) {
        logError("Save users failed: " . $e->getMessage());
        return false;
    }
}

// Message sending with inline keyboard
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }
        
        $url = API_URL . 'sendMessage?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Send message failed: " . $e->getMessage());
        return false;
    }
}

// Main keyboard
function getMainKeyboard() {
    return [
        [['text' => '💰 Earn', 'callback_data' => 'earn'], ['text' => '💳 Balance', 'callback_data' => 'balance']],
        [['text' => '🏆 Leaderboard', 'callback_data' => 'leaderboard'], ['text' => '👥 Referrals', 'callback_data' => 'referrals']],
        [['text' => '🏧 Withdraw', 'callback_data' => 'withdraw'], ['text' => '❓ Help', 'callback_data' => 'help']]
    ];
}

// Process commands and callbacks
function processUpdate($update) {
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        
        // Create new user if doesn't exist
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        }
        
        if (strpos($text, '/start') === 0) {
            $ref = explode(' ', $text)[1] ?? null;
            if ($ref && !$users[$chat_id]['referred_by']) {
                foreach ($users as $id => $user) {
                    if ($user['ref_code'] === $ref && $id != $chat_id) {
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals']++;
                        $users[$id]['balance'] += 50; // Referral bonus
                        sendMessage($id, "🎉 New referral! +50 points bonus!");
                        break;
                    }
                }
            }
            
            $msg = "👋 Welcome to Earning Bot!\n\n".
                   "💰 Earn points by clicking the Earn button\n".
                   "👥 Invite friends using your referral code\n".
                   "💸 Withdraw your earnings when ready\n\n".
                   "Your referral code: <b>{$users[$chat_id]['ref_code']}</b>\n\n".
                   "Share it with friends to earn 50 points for each referral!";
            sendMessage($chat_id, $msg, getMainKeyboard());
        }
        
    } elseif (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $chat_id = $cb['message']['chat']['id'];
        $data = $cb['data'];
        $message_id = $cb['message']['message_id'];
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'last_earn' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'referred_by' => null
            ];
        }
        
        switch ($data) {
            case 'earn':
                $last_earn = $users[$chat_id]['last_earn'];
                if (time() - $last_earn < 3600) {
                    $wait = 3600 - (time() - $last_earn);
                    $mins = ceil($wait / 60);
                    sendMessage($chat_id, "⏳ Please wait $mins minutes before earning again.");
                } else {
                    $amount = rand(10, 20);
                    $users[$chat_id]['balance'] += $amount;
                    $users[$chat_id]['last_earn'] = time();
                    sendMessage($chat_id, "✅ You earned $amount points!");
                }
                break;
                
            case 'balance':
                $balance = $users[$chat_id]['balance'];
                $refs = $users[$chat_id]['referrals'];
                $msg = "💰 Your balance: <b>$balance points</b>\n";
                $msg .= "👥 Referrals: <b>$refs users</b>\n\n";
                $msg .= "Invite more friends to earn 50 points per referral!";
                sendMessage($chat_id, $msg);
                break;
                
            case 'leaderboard':
                // Sort users by balance
                uasort($users, function($a, $b) {
                    return $b['balance'] - $a['balance'];
                });
                
                $top = 10;
                $msg = "🏆 <b>TOP $top USERS</b> 🏆\n\n";
                $count = 0;
                
                foreach ($users as $id => $user) {
                    if ($count >= $top) break;
                    $msg .= ($count+1) . ". " . ($user['balance'] ?? 0) . " points\n";
                    $count++;
                }
                
                if ($count === 0) {
                    $msg = "No users yet! Be the first to earn points!";
                }
                
                sendMessage($chat_id, $msg);
                break;
                
            case 'referrals':
                $ref_code = $users[$chat_id]['ref_code'];
                $ref_count = $users[$chat_id]['referrals'];
                $msg = "👥 <b>YOUR REFERRALS</b>\n\n";
                $msg .= "Your code: <code>$ref_code</code>\n";
                $msg .= "Total referrals: <b>$ref_count users</b>\n\n";
                $msg .= "Share this link:\n";
                $msg .= "https://t.me/" . $update['callback_query']['message']['chat']['username'] . "?start=$ref_code";
                sendMessage($chat_id, $msg);
                break;
                
            case 'withdraw':
                $balance = $users[$chat_id]['balance'];
                if ($balance < 100) {
                    sendMessage($chat_id, "❌ Minimum withdrawal is 100 points\n\n".
                                           "You currently have: $balance points\n\n".
                                           "Keep earning to reach the minimum!");
                } else {
                    sendMessage($chat_id, "🏧 <b>WITHDRAWAL REQUEST</b>\n\n".
                                          "Your balance: $balance points\n\n".
                                          "Contact @admin with your withdrawal request!");
                }
                break;
                
            case 'help':
                $msg = "❓ <b>HELP CENTER</b> ❓\n\n".
                       "💰 <b>Earn</b>: Collect points every hour\n".
                       "💳 <b>Balance</b>: Check your points\n".
                       "👥 <b>Referrals</b>: Invite friends to earn 50 points each\n".
                       "🏆 <b>Leaderboard</b>: See top earners\n".
                       "🏧 <b>Withdraw</b>: Cash out your points (min 100)\n\n".
                       "Contact @admin for support";
                sendMessage($chat_id, $msg);
                break;
                
            default:
                sendMessage($chat_id, "❌ Unknown command");
                break;
        }
    }
    
    saveUsers($users);
}

// Webhook handling
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update) {
    // Process Telegram update
    processUpdate($update);
    
    // Respond to Telegram
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} else {
    // Health check response for Render.com
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'alive',
        'time' => date('c'),
        'bot' => 'Telegram Earning Bot',
        'users_count' => count(loadUsers())
    ]);
}