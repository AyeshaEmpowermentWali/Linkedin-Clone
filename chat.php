<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$userId = $_SESSION['user_id'];
$userData = getUserData($userId);

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$recipientId = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : 0;

$currentConversation = null;
$messages = [];
$recipientData = null;

// If a recipient_id is provided, find or create a conversation
if ($recipientId > 0) {
    $recipientData = getUserData($recipientId);
    if ($recipientData && $recipientId != $userId) {
        // Try to find an existing conversation between these two users
        $stmt = $conn->prepare("
            SELECT c.id
            FROM conversations c
            JOIN conversation_members cm1 ON c.id = cm1.conversation_id
            JOIN conversation_members cm2 ON c.id = cm2.conversation_id
            WHERE cm1.user_id = ? AND cm2.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $recipientId]);
        $existingConv = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingConv) {
            $conversationId = $existingConv['id'];
        } else {
            // No existing conversation, create a new one
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("INSERT INTO conversations (created_at) VALUES (NOW())");
                $stmt->execute();
                $conversationId = $conn->lastInsertId();

                $stmt = $conn->prepare("INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)");
                $stmt->execute([$conversationId, $userId, $conversationId, $recipientId]);
                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Error creating conversation: " . $e->getMessage());
                $conversationId = 0; // Indicate failure
            }
        }
    } else {
        $recipientId = 0; // Invalid recipient
    }
}

// If a conversation_id is set (either from GET or newly created)
if ($conversationId > 0) {
    // Verify user is a member of this conversation
    $stmt = $conn->prepare("SELECT COUNT(*) FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    if ($stmt->fetchColumn() == 0) {
        $conversationId = 0; // User is not a member
    } else {
        // Fetch conversation details and recipient
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.profile_pic
            FROM conversation_members cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.conversation_id = ? AND cm.user_id != ?
            LIMIT 1
        ");
        $stmt->execute([$conversationId, $userId]);
        $recipientData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch messages
        $stmt = $conn->prepare("
            SELECT m.*, u.name AS sender_name, u.profile_pic AS sender_profile_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content']) && $conversationId > 0) {
    $messageContent = sanitize($_POST['message_content']);
    if (!empty($messageContent)) {
        $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$conversationId, $userId, $messageContent]);
        // Redirect to prevent form resubmission and show new message
        echo "<script>window.location.href = 'chat.php?conversation_id=" . $conversationId . "';</script>";
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - Chat <?php echo $recipientData ? 'with ' . htmlspecialchars($recipientData['name']) : ''; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f3f2ef;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #ffffff;
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-left {
            display: flex;
            align-items: center;
        }

        .logo {
            color: #0a66c2;
            font-size: 32px;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo::before {
            content: "in";
            background: #0a66c2;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            margin-right: 10px;
            font-size: 24px;
            font-weight: bold;
        }

        .search-box {
            background-color: #eef3f8;
            border-radius: 4px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            width: 280px;
            margin-left: 20px;
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            width: 100%;
            margin-left: 5px;
            font-size: 14px;
        }

        .navbar-right {
            display: flex;
            align-items: center;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 12px;
            color: #666666;
            text-decoration: none;
            font-size: 12px;
            padding: 5px 0;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            color: #000000;
        }

        .nav-item.active {
            color: #000000;
            border-bottom: 2px solid #000000;
        }

        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .profile-mini {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }

        .profile-mini img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .container {
            flex: 1;
            max-width: 1128px;
            margin: 20px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 300px 1fr; /* Two columns for conversations and chat */
            gap: 20px;
        }

        .conversations-sidebar {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            height: calc(100vh - 100px); /* Adjust height based on navbar and footer */
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            color: #000;
        }

        .new-message-button {
            background-color: #0a66c2;
            color: #ffffff;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .new-message-button:hover {
            background-color: #084b8a;
        }

        .conversation-list {
            flex-grow: 1;
            overflow-y: auto;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item:hover {
            background-color: #f3f2ef;
        }

        .conversation-item.active {
            background-color: #eef3f8;
        }

        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .conversation-info {
            flex-grow: 1;
        }

        .conversation-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .last-message {
            font-size: 14px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-time {
            font-size: 12px;
            color: #999;
            margin-left: 10px;
        }

        .chat-area {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 100px); /* Adjust height */
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: 600;
            color: #000;
        }

        .messages-list {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            font-size: 15px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .message-bubble.sent {
            background-color: #0a66c2;
            color: #ffffff;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        .message-bubble.received {
            background-color: #eef3f8;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }

        .message-meta {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
            text-align: right;
        }

        .message-bubble.received .message-meta
