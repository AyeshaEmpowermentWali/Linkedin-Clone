<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

// Get user data
$userData = getUserData($_SESSION['user_id']);

$searchQuery = isset($_GET['query']) ? sanitize($_GET['query']) : '';
$searchResults = [];

if (!empty($searchQuery)) {
    // Search for users
    $stmtUsers = $conn->prepare("
        SELECT id, name, headline, profile_pic
        FROM users
        WHERE name LIKE ? OR headline LIKE ?
        LIMIT 10
    ");
    $searchTerm = '%' . $searchQuery . '%';
    $stmtUsers->execute([$searchTerm, $searchTerm]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Search for posts
    $stmtPosts = $conn->prepare("
        SELECT p.id, p.content, p.image, p.created_at, u.name AS author_name, u.profile_pic AS author_profile_pic, u.headline
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.content LIKE ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmtPosts->execute([$searchTerm]);
    $posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

    // Search for jobs
    $stmtJobs = $conn->prepare("
        SELECT j.id, j.title, j.location, j.created_at, u.name AS company_name, u.profile_pic AS company_profile_pic
        FROM jobs j
        JOIN users u ON j.user_id = u.id
        WHERE j.title LIKE ? OR j.location LIKE ? OR u.name LIKE ?
        ORDER BY j.created_at DESC
        LIMIT 10
    ");
    $stmtJobs->execute([$searchTerm, $searchTerm, $searchTerm]);
    $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

    $searchResults = [
        'users' => $users,
        'posts' => $posts,
        'jobs' => $jobs
    ];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - Search Results</title>
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
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .search-results-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            padding: 20px;
        }

        .search-results-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #000;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 15px;
            color: #000;
        }

        .result-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .result-info {
            flex-grow: 1;
        }

        .result-name {
            font-size: 16px;
            font-weight: 600;
            color: #0a66c2;
            text-decoration: none;
            margin-bottom: 2px;
        }

        .result-name:hover {
            text-decoration: underline;
        }

        .result-headline, .result-content, .result-company, .result-location {
            font-size: 14px;
            color: #666;
            margin-bottom: 2px;
        }

        .result-time {
            font-size: 12px;
            color: #999;
        }

        .result-image {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-top: 10px;
        }

        .no-results {
            text-align: center;
            color: #666;
            padding: 20px;
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
        .icon-messaging { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3E%3C/svg%3E"); }
        .icon-notifications { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z'/%3E%3C/svg%3E"); }
        .icon-search { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23000000'%3E%3Cpath d='M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E"); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo">in</a>
            <form action="search.php" method="GET" class="search-box">
                <span class="icon icon-search"></span>
                <input type="text" name="query" placeholder="Search" value="<?php echo htmlspecialchars($searchQuery); ?>">
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
        <div class="search-results-card">
            <h1 class="search-results-title">Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h1>

            <?php if (empty($searchQuery)): ?>
                <p class="no-results">Please enter a search query.</p>
            <?php elseif (empty($searchResults['users']) && empty($searchResults['posts']) && empty($searchResults['jobs'])): ?>
                <p class="no-results">No results found for "<?php echo htmlspecialchars($searchQuery); ?>".</p>
            <?php else: ?>
                <?php if (!empty($searchResults['users'])): ?>
                    <h2 class="section-title">People</h2>
                    <?php foreach ($searchResults['users'] as $user): ?>
                        <div class="result-item">
                            <img src="<?php echo getProfilePicture($user['id']); ?>" alt="Profile" class="result-avatar">
                            <div class="result-info">
                                <a href="profile.php?user_id=<?php echo $user['id']; ?>" class="result-name"><?php echo htmlspecialchars($user['name']); ?></a>
                                <p class="result-headline"><?php echo htmlspecialchars($user['headline']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($searchResults['posts'])): ?>
                    <h2 class="section-title">Posts</h2>
                    <?php foreach ($searchResults['posts'] as $post): ?>
                        <div class="result-item" style="align-items: flex-start;">
                            <img src="<?php echo getProfilePicture($post['author_profile_pic']); ?>" alt="Author" class="result-avatar">
                            <div class="result-info">
                                <p class="result-name"><?php echo htmlspecialchars($post['author_name']); ?></p>
                                <p class="result-headline"><?php echo htmlspecialchars($post['headline']); ?></p>
                                <p class="result-content"><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 150))); ?>...</p>
                                <?php if ($post['image']): ?>
                                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="result-image">
                                <?php endif; ?>
                                <p class="result-time"><?php echo timeAgo($post['created_at']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($searchResults['jobs'])): ?>
                    <h2 class="section-title">Jobs</h2>
                    <?php foreach ($searchResults['jobs'] as $job): ?>
                        <div class="result-item">
                            <img src="<?php echo getProfilePicture($job['company_profile_pic']); ?>" alt="Company" class="result-avatar">
                            <div class="result-info">
                                <p class="result-name"><?php echo htmlspecialchars($job['title']); ?></p>
                                <p class="result-company"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                <p class="result-location"><?php echo htmlspecialchars($job['location']); ?></p>
                                <p class="result-time"><?php echo timeAgo($job['created_at']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>
</body>
</html>
