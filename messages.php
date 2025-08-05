<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

// Get user data
$userData = getUserData($_SESSION['user_id']);

// Fetch conversations for the logged-in user
$stmt = $conn->prepare("
    SELECT 
        cm.conversation_id,
        u.id AS other_user_id,
        u.name AS other_user_name,
        u.profile_pic AS other_user_profile_pic,
        MAX(m.created_at) AS last_message_time,
        SUBSTRING(MAX(CONCAT(m.created_at, m.message)), 20) AS last_message_content
    FROM conversation_members cm
    JOIN conversations c ON cm.conversation_id = c.id
    JOIN conversation_members cm2 ON c.id = cm2.conversation_id AND cm2.user_id != ?
    JOIN users u ON cm2.user_id = u.id
    LEFT JOIN messages m ON c.id = m.conversation_id
    WHERE cm.user_id = ?
    GROUP BY cm.conversation_id, u.id, u.name, u.profile_pic
    ORDER BY last_message_time DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - Messaging</title>
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
            justify-content: center;
            align-items: center;
            font-size: 18px;
            color: #666;
            height: calc(100vh - 100px); /* Adjust height */
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            font-size: 12px;
            color: #666666;
            margin-top: auto;
        }

        /* Icon styles */
        .icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .icon-home { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z'/%3E%3C/svg%3E"); }
        .icon-network { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z'/%3E%3C/svg%3E"); }
        .icon-jobs { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z'/%3E%3C/svg%3E"); }
        .icon-messaging { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23000000'%3E%3Cpath d='M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3E%3C/svg%3E"); }
        .icon-notifications { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z'/%3E%3C/svg%3E"); }
        .icon-search { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E"); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo">in</a>
            <form action="search.php" method="GET" class="search-box">
                <span class="icon icon-search"></span>
                <input type="text" name="query" placeholder="Search">
            </form>
        </div>
        <div class="navbar-right">
            <a href="index.php" class="nav-item">
                <span class="icon icon-home"></span>
                <span>Home</span>
            </a>
            <a href="connections.php" class="nav-item">
                <span class="icon icon-network"></span>
                <span>My Network</span>
            </a>
            <a href="jobs.php" class="nav-item">
                <span class="icon icon-jobs"></span>
                <span>Jobs</span>
            </a>
            <a href="messages.php" class="nav-item active">
                <span class="icon icon-messaging"></span>
                <span>Messaging</span>
            </a>
            <a href="notifications.php" class="nav-item">
                <span class="icon icon-notifications"></span>
                <span>Notifications</span>
            </a>
            <a href="articles.php" class="nav-item">
                <span class="icon icon-article"></span>
                <span>Articles</span>
            </a>
            <a href="profile.php" class="nav-item profile-mini">
                <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>" alt="Profile">
                <span>Me</span>
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">Messaging</h2>
                <a href="new-message.php" class="new-message-button">New Message</a>
            </div>
            <div class="conversation-list">
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="chat.php?conversation_id=<?php echo $conv['conversation_id']; ?>" class="conversation-item">
                            <img src="<?php echo getProfilePicture($conv['other_user_id']); ?>" alt="Profile" class="conversation-avatar">
                            <div class="conversation-info">
                                <div class="conversation-name"><?php echo htmlspecialchars($conv['other_user_name']); ?></div>
                                <div class="last-message"><?php echo htmlspecialchars($conv['last_message_content']); ?></div>
                            </div>
                            <div class="message-time"><?php echo timeAgo($conv['last_message_time']); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="padding: 15px; text-align: center; color: #666;">No conversations yet. Start a new message!</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="chat-area">
            Select a conversation to view messages.
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>
</body>
</html>
