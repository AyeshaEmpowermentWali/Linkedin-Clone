<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

// Determine which user's profile to display
$displayUserId = $_SESSION['user_id']; // Default to logged-in user

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $requestedUserId = (int)$_GET['user_id'];
    // Only allow viewing other profiles if they are not the current user
    if ($requestedUserId !== $_SESSION['user_id']) {
        $displayUserId = $requestedUserId;
    }
}

// Get user data for the determined user
$userData = getUserData($displayUserId);

// If user data is not found (e.g., invalid user_id in URL)
if (!$userData) {
    // Fallback to logged-in user's profile or show an error
    $userData = getUserData($_SESSION['user_id']);
    if (!$userData) { // If even logged-in user data is not found, something is seriously wrong
        echo "<script>alert('User data not found. Please log in again.'); window.location.href = 'login.php';</script>";
        exit;
    }
    // Optionally, redirect to the logged-in user's profile URL to clean up the URL
    if (isset($_GET['user_id'])) {
        echo "<script>window.location.href = 'profile.php';</script>";
        exit;
    }
}

// Fetch user's experience
$stmt = $conn->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY start_date DESC");
$stmt->execute([$displayUserId]);
$userExperience = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's education
$stmt = $conn->prepare("SELECT * FROM education WHERE user_id = ? ORDER BY start_date DESC");
$stmt->execute([$displayUserId]);
$userEducation = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's skills
$stmt = $conn->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY skill_name ASC");
$stmt->execute([$displayUserId]);
$userSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's posts with like counts and user's like status
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        u.name, 
        u.headline, 
        u.profile_pic,
        COUNT(l.id) AS like_count,
        MAX(CASE WHEN l.user_id = ? THEN 1 ELSE 0 END) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $displayUserId]);
$userPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - <?php echo htmlspecialchars($userData['name']); ?>'s Profile</title>
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
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-main-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .profile-background {
            height: 120px;
            background: linear-gradient(to right, #0a66c2, #0077b5);
            position: relative;
        }

        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            margin: -60px auto 0; /* Pulls avatar up over background */
            background-color: #fff; /* Fallback for transparent images */
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            padding: 20px;
            text-align: center;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #000;
        }

        .profile-headline {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }

        .profile-location, .profile-industry {
            font-size: 14px;
            color: #999;
            margin-bottom: 5px;
        }

        .profile-about-card {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
        }

        .profile-section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #000;
        }

        .profile-about-content {
            font-size: 15px;
            line-height: 1.6;
            color: #333;
            white-space: pre-wrap; /* Preserves whitespace and line breaks */
        }

        .section-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .section-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .item-title {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin-bottom: 5px;
        }
        .item-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .item-date-location {
            font-size: 13px;
            color: #999;
            margin-bottom: 5px;
        }
        .item-description {
            font-size: 14px;
            color: #333;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .skill-tag {
            background-color: #eef3f8;
            color: #333;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 500;
        }

        .post-card {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px; /* Add margin between posts */
        }

        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .post-user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .post-user-info {
            flex-grow: 1;
        }

        .post-user-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .post-user-headline {
            font-size: 12px;
            color: #666666;
            margin-bottom: 2px;
        }

        .post-time {
            font-size: 12px;
            color: #666666;
        }

        .post-content {
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .post-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover; /* Changed from contain to cover */
            border-radius: 4px;
            margin-bottom: 15px;
            height: auto; /* Ensure aspect ratio is maintained */
        }

        .post-stats {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666666;
        }

        .post-buttons {
            display: flex;
            justify-content: space-around;
        }

        .post-button {
            display: flex;
            align-items: center;
            color: #666666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 4px;
        }

        .post-button.liked {
            color: #0a66c2; /* Blue color for liked state */
        }

        .post-button:hover {
            background-color: #f3f2ef;
        }

        .post-button i {
            margin-right: 8px;
            font-size: 20px;
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            font-size: 12px;
            color: #666666;
            margin-top: auto;
        }

        /* Icon styles (from previous files) */
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
        .icon-search { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E"); }
        .icon-article { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z'/%3E%3C/svg%3E"); }
        .icon-like { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1.91l-.01-.01L23 10z'/%3E%3C/svg%3E"); }
        .icon-comment { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3E%3C/svg%3E"); }
        .icon-share { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z'/%3E%3C/svg%3E"); }
        .icon-send { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'/%3E%3C/svg%3E"); }
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
            <a href="profile.php" class="nav-item active profile-mini">
                <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>" alt="Profile">
                <span>Me</span>
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="profile-main-card">
            <div class="profile-background"></div>
            <div class="profile-avatar-container">
                <img src="<?php echo getProfilePicture($userData['id']); ?>" alt="Profile" class="profile-avatar">
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($userData['name']); ?></h1>
                <p class="profile-headline"><?php echo htmlspecialchars($userData['headline'] ? $userData['headline'] : 'Add a headline'); ?></p>
                <p class="profile-location"><?php echo htmlspecialchars($userData['location'] ? $userData['location'] : 'Location not specified'); ?></p>
                <p class="profile-industry"><?php echo htmlspecialchars($userData['industry'] ? $userData['industry'] : 'Industry not specified'); ?></p>
                <!-- Add buttons for "Open to", "Add profile section", "More" here later -->
            </div>
        </div>

        <div class="profile-about-card">
            <h2 class="profile-section-title">About</h2>
            <p class="profile-about-content">
                <?php echo nl2br(htmlspecialchars($userData['about'] ? $userData['about'] : 'Tell us about yourself! Click here to add your summary.')); ?>
            </p>
        </div>

        <!-- Experience Section -->
        <div class="profile-about-card">
            <h2 class="profile-section-title">Experience</h2>
            <?php if (count($userExperience) > 0): ?>
                <?php foreach ($userExperience as $experience): ?>
                    <div class="section-item">
                        <h3 class="item-title"><?php echo htmlspecialchars($experience['title']); ?></h3>
                        <p class="item-subtitle"><?php echo htmlspecialchars($experience['company']); ?></p>
                        <p class="item-date-location">
                            <?php echo htmlspecialchars(date('M Y', strtotime($experience['start_date']))); ?> -
                            <?php echo $experience['current'] ? 'Present' : htmlspecialchars(date('M Y', strtotime($experience['end_date']))); ?>
                            (<?php echo htmlspecialchars($experience['location']); ?>)
                        </p>
                        <?php if ($experience['description']): ?>
                            <p class="item-description"><?php echo nl2br(htmlspecialchars($experience['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="profile-about-content">No experience added yet.</p>
            <?php endif; ?>
        </div>

        <!-- Education Section -->
        <div class="profile-about-card">
            <h2 class="profile-section-title">Education</h2>
            <?php if (count($userEducation) > 0): ?>
                <?php foreach ($userEducation as $education): ?>
                    <div class="section-item">
                        <h3 class="item-title"><?php echo htmlspecialchars($education['school']); ?></h3>
                        <p class="item-subtitle">
                            <?php echo htmlspecialchars($education['degree']); ?> in <?php echo htmlspecialchars($education['field']); ?>
                        </p>
                        <p class="item-date-location">
                            <?php echo htmlspecialchars(date('Y', strtotime($education['start_date']))); ?> -
                            <?php echo htmlspecialchars(date('Y', strtotime($education['end_date']))); ?>
                        </p>
                        <?php if ($education['description']): ?>
                            <p class="item-description"><?php echo nl2br(htmlspecialchars($education['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="profile-about-content">No education added yet.</p>
            <?php endif; ?>
        </div>

        <!-- Skills Section -->
        <div class="profile-about-card">
            <h2 class="profile-section-title">Skills</h2>
            <?php if (count($userSkills) > 0): ?>
                <div class="skills-list">
                    <?php foreach ($userSkills as $skill): ?>
                        <span class="skill-tag"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="profile-about-content">No skills added yet.</p>
            <?php endif; ?>
        </div>

        <!-- User's Posts Section -->
        <div class="profile-about-card">
            <h2 class="profile-section-title">Posts</h2>
            <?php if (count($userPosts) > 0): ?>
                <?php foreach ($userPosts as $post): ?>
                    <div class="post-card">
                        <div class="post-header">
                            <img src="<?php echo getProfilePicture($post['user_id']); ?>" alt="Profile" class="post-user-avatar">
                            <div class="post-user-info">
                                <h3 class="post-user-name"><?php echo htmlspecialchars($post['name']); ?></h3>
                                <p class="post-user-headline"><?php echo htmlspecialchars($post['headline']); ?></p>
                                <p class="post-time"><?php echo timeAgo($post['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        <?php if ($post['image']): ?>
                            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="post-image">
                        <?php endif; ?>
                        <div class="post-stats">
                            <div>
                                <span class="like-count-display" data-post-id="<?php echo $post['id']; ?>"><?php echo $post['like_count']; ?> likes</span> • <span>8 comments</span>
                            </div>
                        </div>
                        <div class="post-buttons">
                            <div class="post-button like-button <?php echo $post['user_liked'] ? 'liked' : ''; ?>" data-post-id="<?php echo $post['id']; ?>">
                                <span class="icon icon-like"></span>
                                Like
                            </div>
                            <div class="post-button comment-button">
                                <span class="icon icon-comment"></span>
                                Comment
                            </div>
                            <div class="post-button share-button">
                                <span class="icon icon-share"></span>
                                Share
                            </div>
                            <div class="post-button send-button">
                                <span class="icon icon-send"></span>
                                Send
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="profile-about-content">No posts by this user yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>
    <script>
        // Post interaction buttons
        document.querySelectorAll('.like-button').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                const likeCountDisplay = document.querySelector(`.like-count-display[data-post-id="${postId}"]`);
                
                fetch('handle_post_interaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=like_post&post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.toggle('liked', data.liked);
                        likeCountDisplay.textContent = `${data.like_count} likes`;
                        console.log(data.message);
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => console.error('Error liking post:', error));
            });
        });

        document.querySelectorAll('.comment-button').forEach(button => {
            button.addEventListener('click', function() {
                alert('Comment functionality coming soon!');
            });
        });

        document.querySelectorAll('.share-button').forEach(button => {
            button.addEventListener('click', function() {
                alert('Share functionality coming soon!');
            });
        });

        document.querySelectorAll('.send-button').forEach(button => {
            button.addEventListener('click', function() {
                alert('Send functionality coming soon!');
            });
        });
    </script>
</body>
</html>
