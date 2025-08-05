<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$userId = $_SESSION['user_id'];

// Handle connection requests (accept/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);
    $connectionId = isset($_POST['connection_id']) ? (int)$_POST['connection_id'] : 0;

    if ($connectionId > 0) {
        if ($action === 'accept') {
            $stmt = $conn->prepare("UPDATE connections SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$connectionId, $userId]);
            echo json_encode(['success' => true, 'message' => 'Connection accepted.']);
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("DELETE FROM connections WHERE id = ? AND receiver_id = ?");
            $stmt->execute([$connectionId, $userId]);
            echo json_encode(['success' => true, 'message' => 'Connection rejected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } else if (isset($_POST['receiver_id'])) { // Handle new connection request
        $receiverId = (int)$_POST['receiver_id'];
        if ($receiverId > 0 && $receiverId != $userId) {
            // Check if a request already exists (pending or accepted)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
            $stmt->execute([$userId, $receiverId, $receiverId, $userId]);
            $existingConnection = $stmt->fetchColumn();

            if ($existingConnection == 0) {
                $stmt = $conn->prepare("INSERT INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$userId, $receiverId]);
                echo json_encode(['success' => true, 'message' => 'Connection request sent.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Connection request already exists or is accepted.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid receiver ID.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No action or receiver ID specified.']);
    }
    exit;
}

// Get user data
$userData = getUserData($userId);

// Fetch pending connection requests
$stmt = $conn->prepare("
    SELECT c.id, u.id as requester_id, u.name, u.headline, u.profile_pic
    FROM connections c
    JOIN users u ON c.requester_id = u.id
    WHERE c.receiver_id = ? AND c.status = 'pending'
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch accepted connections
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.headline, u.profile_pic
    FROM connections c
    JOIN users u ON (c.requester_id = u.id AND c.receiver_id = ?) OR (c.receiver_id = u.id AND c.requester_id = ?)
    WHERE c.status = 'accepted' AND u.id != ?
    ORDER BY u.name ASC
");
$stmt->execute([$userId, $userId, $userId]);
$myConnections = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - My Network</title>
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
            grid-template-columns: 250px 1fr; /* Sidebar and main content */
            gap: 20px;
        }

        .network-sidebar {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .sidebar-section-title {
            font-size: 16px;
            font-weight: 600;
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #000;
        }

        .sidebar-nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .sidebar-nav-item:hover {
            background-color: #f3f2ef;
        }

        .sidebar-nav-item span {
            margin-left: 10px;
        }

        .sidebar-nav-item .count {
            margin-left: auto;
            color: #0a66c2;
            font-weight: 600;
        }

        .main-network-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .network-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            padding: 20px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #000;
        }

        .connection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .connection-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
            padding-bottom: 15px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .connection-background {
            height: 50px;
            background: linear-gradient(to right, #0a66c2, #0077b5);
            margin-bottom: 30px; /* Space for avatar */
        }

        .connection-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            margin-top: -40px; /* Pulls avatar up */
            object-fit: cover;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .connection-name {
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            margin-bottom: 5px;
            color: #000;
        }

        .connection-headline {
            font-size: 13px;
            color: #666;
            padding: 0 10px;
            margin-bottom: 10px;
        }

        .connect-button {
            background-color: transparent;
            border: 1px solid #0a66c2;
            color: #0a66c2;
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .connect-button:hover {
            background-color: rgba(10, 102, 194, 0.1);
        }

        .request-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .request-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .request-info {
            flex-grow: 1;
        }

        .request-name {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin-bottom: 2px;
        }

        .request-headline {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .request-actions button {
            background-color: transparent;
            border: 1px solid #0a66c2;
            color: #0a66c2;
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 10px;
            transition: background-color 0.2s ease;
        }

        .request-actions button.accept {
            background-color: #0a66c2;
            color: #ffffff;
        }

        .request-actions button.accept:hover {
            background-color: #084b8a;
        }

        .request-actions button.ignore:hover {
            background-color: #f3f2ef;
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
        .icon-network { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23000000'%3E%3Cpath d='M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z'/%3E%3C/svg%3E"); }
        .icon-jobs { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z'/%3E%3C/svg%3E"); }
        .icon-messaging { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3E%3C/svg%3E"); }
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
            <a href="connections.php" class="nav-item active">
                <span class="icon icon-network"></span>
                <span>My Network</span>
            </a>
            <a href="jobs.php" class="nav-item">
                <span class="icon icon-jobs"></span>
                <span>Jobs</span>
            </a>
            <a href="messages.php" class="nav-item">
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
        <div class="network-sidebar">
            <div class="sidebar-section-title">Manage my network</div>
            <a href="#" class="sidebar-nav-item">
                <span class="icon icon-network"></span>
                <span>Connections</span>
                <span class="count"><?php echo count($myConnections); ?></span>
            </a>
            <a href="#" class="sidebar-nav-item">
                <span class="icon icon-messaging"></span>
                <span>Pending Invitations</span>
                <span class="count"><?php echo count($pendingRequests); ?></span>
            </a>
            <!-- Add more network management links here -->
        </div>

        <div class="main-network-content">
            <?php if (count($pendingRequests) > 0): ?>
                <div class="network-card">
                    <h2 class="card-title">Invitations (<?php echo count($pendingRequests); ?>)</h2>
                    <?php foreach ($pendingRequests as $request): ?>
                        <div class="request-item">
                            <img src="<?php echo getProfilePicture($request['requester_id']); ?>" alt="Profile" class="request-avatar">
                            <div class="request-info">
                                <div class="request-name"><?php echo htmlspecialchars($request['name']); ?></div>
                                <div class="request-headline"><?php echo htmlspecialchars($request['headline']); ?></div>
                            </div>
                            <div class="request-actions">
                                <button class="ignore-button" data-connection-id="<?php echo $request['id']; ?>">Ignore</button>
                                <button class="accept-button" data-connection-id="<?php echo $request['id']; ?>">Accept</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="network-card">
                <h2 class="card-title">My Connections (<?php echo count($myConnections); ?>)</h2>
                <?php if (count($myConnections) > 0): ?>
                    <div class="connection-grid">
                        <?php foreach ($myConnections as $connection): ?>
                            <div class="connection-item">
                                <div class="connection-background"></div>
                                <img src="<?php echo getProfilePicture($connection['id']); ?>" alt="Profile" class="connection-avatar">
                                <h3 class="connection-name"><?php echo htmlspecialchars($connection['name']); ?></h3>
                                <p class="connection-headline"><?php echo htmlspecialchars($connection['headline']); ?></p>
                                <a href="profile.php?user_id=<?php echo $connection['id']; ?>" class="connect-button">View Profile</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666;">You don't have any connections yet. Start connecting!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>

    <script>
        document.querySelectorAll('.accept-button').forEach(button => {
            button.addEventListener('click', function() {
                const connectionId = this.getAttribute('data-connection-id');
                fetch('connections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=accept&connection_id=${connectionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Reload to update the list
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => console.error('Error accepting connection:', error));
            });
        });

        document.querySelectorAll('.ignore-button').forEach(button => {
            button.addEventListener('click', function() {
                const connectionId = this.getAttribute('data-connection-id');
                fetch('connections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reject&connection_id=${connectionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Reload to update the list
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => console.error('Error ignoring connection:', error));
            });
        });
    </script>
</body>
</html>
