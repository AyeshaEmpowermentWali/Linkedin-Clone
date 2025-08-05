<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

$userId = $_SESSION['user_id'];
$userData = getUserData($userId);

$recipientId = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : 0;
$recipientData = null;

if ($recipientId > 0) {
    $recipientData = getUserData($recipientId);
    if (!$recipientData) {
        $recipientId = 0; // Reset if invalid recipient
    }
}

$searchUsers = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_query'])) {
    $searchQuery = sanitize($_POST['search_query']);
    if (!empty($searchQuery)) {
        $stmt = $conn->prepare("SELECT id, name, headline, profile_pic FROM users WHERE name LIKE ? AND id != ? LIMIT 10");
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->execute([$searchTerm, $userId]);
        $searchUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - New Message</title>
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
            max-width: 700px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .new-message-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            padding: 20px;
        }

        .card-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #000;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 15px;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        textarea:focus {
            border-color: #0a66c2;
            outline: none;
            box-shadow: 0 0 0 1px #0a66c2;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-top: 10px;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f3f2ef;
        }

        .search-result-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .search-result-info {
            flex-grow: 1;
        }

        .search-result-name {
            font-size: 15px;
            font-weight: 600;
            color: #000;
        }

        .search-result-headline {
            font-size: 13px;
            color: #666;
        }

        .selected-recipient {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background-color: #eef3f8;
            border-radius: 20px;
            margin-top: 10px;
            width: fit-content;
        }

        .selected-recipient-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }

        .selected-recipient-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .remove-recipient {
            background: none;
            border: none;
            color: #666;
            font-size: 18px;
            margin-left: 8px;
            cursor: pointer;
        }

        button[type="submit"] {
            background-color: #0a66c2;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        button[type="submit"]:hover {
            background-color: #084b8a;
        }

        button[type="submit"]:disabled {
            background-color: #e0e0e0;
            cursor: not-allowed;
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
        <div class="new-message-card">
            <h1 class="card-title">New Message</h1>
            <form action="chat.php" method="GET" id="messageForm">
                <div class="form-group">
                    <label for="recipient_search">To:</label>
                    <?php if ($recipientData): ?>
                        <div class="selected-recipient" id="selectedRecipient">
                            <img src="<?php echo getProfilePicture($recipientData['id']); ?>" alt="Recipient" class="selected-recipient-avatar">
                            <span class="selected-recipient-name"><?php echo htmlspecialchars($recipientData['name']); ?></span>
                            <button type="button" class="remove-recipient" id="removeRecipient">&times;</button>
                            <input type="hidden" name="recipient_id" id="hiddenRecipientId" value="<?php echo $recipientData['id']; ?>">
                        </div>
                    <?php else: ?>
                        <input type="text" id="recipient_search" placeholder="Search for a person" autocomplete="off">
                        <input type="hidden" name="recipient_id" id="hiddenRecipientId">
                        <div class="search-results" id="searchResults">
                            <!-- Search results will be populated here -->
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="message_content">Message:</label>
                    <textarea id="message_content" name="message" placeholder="Write your message..." required></textarea>
                </div>
                <button type="submit" id="sendMessageButton" <?php echo $recipientData ? '' : 'disabled'; ?>>Send Message</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>

    <script>
        const recipientSearchInput = document.getElementById('recipient_search');
        const searchResultsDiv = document.getElementById('searchResults');
        const hiddenRecipientIdInput = document.getElementById('hiddenRecipientId');
        const sendMessageButton = document.getElementById('sendMessageButton');
        const selectedRecipientDiv = document.getElementById('selectedRecipient');
        const removeRecipientButton = document.getElementById('removeRecipient');

        if (recipientSearchInput) {
            recipientSearchInput.addEventListener('input', debounce(function() {
                const query = this.value;
                if (query.length > 2) {
                    fetch('new-message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `search_query=${encodeURIComponent(query)}`
                    })
                    .then(response => response.text()) // Get as text first
                    .then(text => {
                        try {
                            const data = JSON.parse(text); // Try to parse as JSON
                            searchResultsDiv.innerHTML = '';
                            if (data.users && data.users.length > 0) {
                                data.users.forEach(user => {
                                    const item = document.createElement('div');
                                    item.classList.add('search-result-item');
                                    item.innerHTML = `
                                        <img src="${user.profile_pic}" alt="Profile" class="search-result-avatar">
                                        <div class="search-result-info">
                                            <div class="search-result-name">${user.name}</div>
                                            <div class="search-result-headline">${user.headline}</div>
                                        </div>
                                    `;
                                    item.addEventListener('click', () => {
                                        selectRecipient(user.id, user.name, user.profile_pic);
                                    });
                                    searchResultsDiv.appendChild(item);
                                });
                            } else {
                                searchResultsDiv.innerHTML = '<div style="padding: 10px; text-align: center; color: #666;">No users found.</div>';
                            }
                        } catch (error) {
                            console.error('Failed to parse JSON response:', text, error);
                            searchResultsDiv.innerHTML = '<div style="padding: 10px; text-align: center; color: #e00;">Error loading results.</div>';
                        }
                    })
                    .catch(error => console.error('Search fetch error:', error));
                } else {
                    searchResultsDiv.innerHTML = '';
                }
            }, 300)); // Debounce to limit API calls
        }

        if (removeRecipientButton) {
            removeRecipientButton.addEventListener('click', () => {
                hiddenRecipientIdInput.value = '';
                selectedRecipientDiv.style.display = 'none';
                recipientSearchInput.style.display = 'block';
                recipientSearchInput.value = '';
                sendMessageButton.disabled = true;
            });
        }

        function selectRecipient(id, name, profilePic) {
            hiddenRecipientIdInput.value = id;
            recipientSearchInput.style.display = 'none';
            searchResultsDiv.innerHTML = '';
            
            if (selectedRecipientDiv) {
                selectedRecipientDiv.innerHTML = `
                    <img src="${profilePic}" alt="Recipient" class="selected-recipient-avatar">
                    <span class="selected-recipient-name">${name}</span>
                    <button type="button" class="remove-recipient" id="removeRecipientBtn">&times;</button>
                    <input type="hidden" name="recipient_id" id="hiddenRecipientId" value="${id}">
                `;
                selectedRecipientDiv.style.display = 'flex';
                document.getElementById('removeRecipientBtn').addEventListener('click', () => {
                    hiddenRecipientIdInput.value = '';
                    selectedRecipientDiv.style.display = 'none';
                    recipientSearchInput.style.display = 'block';
                    recipientSearchInput.value = '';
                    sendMessageButton.disabled = true;
                });
            }
            sendMessageButton.disabled = false;
        }

        // Debounce function
        function debounce(func, delay) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }

        // Initial state check for send button
        if (hiddenRecipientIdInput && hiddenRecipientIdInput.value) {
            sendMessageButton.disabled = false;
        } else {
            sendMessageButton.disabled = true;
        }
    </script>
</body>
</html>
