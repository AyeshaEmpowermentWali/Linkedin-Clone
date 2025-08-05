<?php
require_once 'db.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

// Get user data
$userData = getUserData($_SESSION['user_id']);

// Get posts for feed with like counts and user's like status
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
    LEFT JOIN connections c ON (c.requester_id = ? AND c.receiver_id = p.user_id AND c.status = 'accepted') OR (c.receiver_id = ? AND c.requester_id = p.user_id AND c.status = 'accepted')
    LEFT JOIN likes l ON p.id = l.post_id
    WHERE p.user_id = ? OR c.status = 'accepted'
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get connection suggestions
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.headline, u.profile_pic 
    FROM users u
    WHERE u.id != ? 
    AND NOT EXISTS (
        SELECT 1 FROM connections c 
        WHERE (c.requester_id = ? AND c.receiver_id = u.id) 
        OR (c.receiver_id = ? AND c.requester_id = u.id)
    )
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent job postings
$stmt = $conn->prepare("
    SELECT j.*, u.name as company_name
    FROM jobs j
    JOIN users u ON j.user_id = u.id
    ORDER BY j.created_at DESC
    LIMIT 5
");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content'])) {
    $content = sanitize($_POST['post_content']);
    $userId = $_SESSION['user_id'];
    
    // Handle image upload if present
    $image = '';
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $target_file = $target_dir . time() . '_' . basename($_FILES["post_image"]["name"]);
        if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $content, $image]);
    
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Clone - Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f3f2ef;
            color: #000000;
        }
        
        .navbar {
            background-color: #ffffff;
            padding: 0 24px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
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
            font-size: 28px;
            font-weight: bold;
            margin-right: 10px;
            text-decoration: none;
        }
        
        .search-box {
            background-color: #eef3f8;
            border-radius: 4px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            width: 280px;
        }
        
        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            width: 100%;
            margin-left: 5px;
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
        }
        
        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .nav-item.active {
            color: #000000;
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
            max-width: 1128px;
            margin: 72px auto 0;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 225px 1fr 300px;
            gap: 24px;
        }
        
        .sidebar {
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 72px;
            height: fit-content;
        }
        
        .profile-card {
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .profile-background {
            height: 60px;
            background: linear-gradient(to right, #0a66c2, #0077b5);
        }
        
        .profile-image {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            margin-top: -36px;
            object-fit: cover;
        }
        
        .profile-name {
            font-size: 16px;
            font-weight: 600;
            margin: 10px 0 5px;
        }
        
        .profile-headline {
            font-size: 12px;
            color: #666666;
            padding: 0 10px;
            margin-bottom: 10px;
        }
        
        .profile-stats {
            padding: 10px 15px;
            text-align: left;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            color: #0a66c2;
            font-weight: 600;
        }
        
        .premium-banner {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .premium-text {
            font-size: 12px;
            color: #666666;
        }
        
        .premium-link {
            color: #915907;
            font-weight: 600;
            text-decoration: none;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .post-form {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
        }
        
        .post-input-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .post-input {
            flex-grow: 1;
            border: 1px solid #e0e0e0;
            border-radius: 35px;
            padding: 12px 15px;
            cursor: pointer;
            background-color: #ffffff;
            color: #666666;
            font-size: 14px;
            outline: none;
            resize: none;
        }
        
        .post-input:focus {
            border-color: #0a66c2;
        }
        
        .post-actions {
            display: flex;
            justify-content: space-around;
        }
        
        .post-action {
            display: flex;
            align-items: center;
            color: #666666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 4px;
        }
        
        .post-action:hover {
            background-color: #f3f2ef;
        }
        
        .post-action i {
            margin-right: 8px;
            font-size: 20px;
        }
        
        .post-card {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
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
            object-fit: contain;
            border-radius: 4px;
            margin-bottom: 15px;
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
        
        .right-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-card {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
        }
        
        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .connection-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .connection-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .connection-info {
            flex-grow: 1;
        }
        
        .connection-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .connection-headline {
            font-size: 12px;
            color: #666666;
            margin-bottom: 5px;
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
        }
        
        .connect-button:hover {
            background-color: rgba(10, 102, 194, 0.1);
        }
        
        .job-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .job-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .job-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .job-company {
            font-size: 12px;
            color: #666666;
            margin-bottom: 5px;
        }
        
        .job-location {
            font-size: 12px;
            color: #666666;
            margin-bottom: 5px;
        }
        
        .job-time {
            font-size: 12px;
            color: #666666;
        }
        
        .view-all {
            color: #666666;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            margin-top: 10px;
            cursor: pointer;
        }
        
        .view-all:hover {
            color: #0a66c2;
        }
        
        .footer {
            text-align: center;
            padding: 20px 0;
            font-size: 12px;
            color: #666666;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: #ffffff;
            border-radius: 10px;
            width: 100%;
            max-width: 600px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #666666;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
        }
        
        .post-textarea {
            width: 100%;
            min-height: 100px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
            resize: none;
            font-size: 14px;
        }
        
        .post-textarea:focus {
            border-color: #0a66c2;
            outline: none;
        }
        
        .post-button-primary {
            background-color: #0a66c2;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .post-button-primary:hover {
            background-color: #084b8a;
        }
        
        .post-button-primary:disabled {
            background-color: #e0e0e0;
            cursor: not-allowed;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: none;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .container {
                grid-template-columns: 225px 1fr;
            }
            
            .right-sidebar {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 0 10px;
            }
            
            .sidebar {
                display: none;
            }
            
            .navbar {
                padding: 0 10px;
            }
            
            .search-box {
                width: 200px;
            }
            
            .nav-item span {
                display: none;
            }
        }
        
        /* Font Awesome icons replacement */
        .icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        
        .icon-home {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z'/%3E%3C/svg%3E");
        }
        
        .icon-network {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23000000'%3E%3Cpath d='M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z'/%3E%3C/svg%3E");
        }
        
        .icon-jobs {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z'/%3E%3C/svg%3E");
        }
        
        .icon-messaging {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3E%3C/svg%3E");
        }
        
        .icon-notifications {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z'/%3E%3C/svg%3E");
        }
        
        .icon-search {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E");
        }
        
        .icon-photo {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z'/%3E%3C/svg%3E");
        }
        
        .icon-video {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z'/%3E%3C/svg%3E");
        }
        
        .icon-event {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z'/%3E%3C/svg%3E");
        }
        
        .icon-article {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z'/%3E%3C/svg%3E");
        }
        
        .icon-like {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1.91l-.01-.01L23 10z'/%3E%3C/svg%3E");
        }
        
        .icon-comment {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'/%3C/svg%3E");
        }
        
        .icon-share {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z'/%3E%3C/svg%3E");
        }
        
        .icon-send {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666666'%3E%3Cpath d='M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'/%3E%3C/svg%3E");
        }
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
            <a href="index.php" class="nav-item active">
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
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="profile-card">
                <div class="profile-background"></div>
                <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>" alt="Profile" class="profile-image">
                <h2 class="profile-name"><?php echo $userData['name']; ?></h2>
                <p class="profile-headline"><?php echo $userData['headline'] ? $userData['headline'] : 'Add a headline'; ?></p>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <span>Who viewed your profile</span>
                    <span class="stat-value">38</span>
                </div>
                <div class="stat-item">
                    <span>Views of your post</span>
                    <span class="stat-value">96</span>
                </div>
            </div>
            <div class="premium-banner">
                <p class="premium-text">Access exclusive tools & insights</p>
                <a href="#" class="premium-link">Try Premium for free</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Post Form -->
            <div class="post-form">
                <div class="post-input-container">
                    <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>" alt="Profile" class="post-avatar">
                    <div class="post-input" id="open-post-modal">Start a post</div>
                </div>
                <div class="post-actions">
                    <div class="post-action">
                        <span class="icon icon-photo"></span>
                        Photo
                    </div>
                    <div class="post-action">
                        <span class="icon icon-video"></span>
                        Video
                    </div>
                    <div class="post-action">
                        <span class="icon icon-event"></span>
                        Event
                    </div>
                    <div class="post-action">
                        <span class="icon icon-article"></span>
                        Write article
                    </div>
                </div>
            </div>

            <!-- Posts -->
            <?php foreach ($posts as $post): ?>
            <div class="post-card">
                <div class="post-header">
                    <img src="<?php echo $post['profile_pic']; ?>" alt="Profile" class="post-user-avatar">
                    <div class="post-user-info">
                        <h3 class="post-user-name"><?php echo $post['name']; ?></h3>
                        <p class="post-user-headline"><?php echo $post['headline']; ?></p>
                        <p class="post-time"><?php echo timeAgo($post['created_at']); ?></p>
                    </div>
                </div>
                <div class="post-content">
                    <?php echo nl2br($post['content']); ?>
                </div>
                <?php if ($post['image']): ?>
                <img src="<?php echo $post['image']; ?>" alt="Post image" class="post-image">
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
        </div>

        <!-- Right Sidebar -->
        <div class="right-sidebar">
            <!-- Connection Suggestions -->
            <div class="sidebar-card">
                <h2 class="sidebar-title">Add to your feed</h2>
                <?php foreach ($suggestions as $suggestion): ?>
                <div class="connection-item">
                    <img src="<?php echo $suggestion['profile_pic']; ?>" alt="Profile" class="connection-avatar">
                    <div class="connection-info">
                        <h3 class="connection-name"><?php echo $suggestion['name']; ?></h3>
                        <p class="connection-headline"><?php echo $suggestion['headline']; ?></p>
                        <button class="connect-button" data-user-id="<?php echo $suggestion['id']; ?>">Connect</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="view-all">View all recommendations</div>
            </div>

            <!-- Job Listings -->
            <div class="sidebar-card">
                <h2 class="sidebar-title">Jobs for you</h2>
                <?php foreach ($jobs as $job): ?>
                <div class="job-item">
                    <h3 class="job-title"><?php echo $job['title']; ?></h3>
                    <p class="job-company"><?php echo $job['company_name']; ?></p>
                    <p class="job-location"><?php echo $job['location']; ?></p>
                    <p class="job-time"><?php echo timeAgo($job['created_at']); ?></p>
                </div>
                <?php endforeach; ?>
                <div class="view-all">See all jobs</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>LinkedIn Clone &copy; <?php echo date('Y'); ?></p>
    </div>

    <!-- Post Modal -->
    <div class="modal" id="post-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create a post</h2>
                <span class="modal-close" id="close-post-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form action="index.php" method="post" enctype="multipart/form-data">
                    <textarea name="post_content" class="post-textarea" placeholder="What do you want to talk about?" required></textarea>
                    <img id="image-preview" class="image-preview">
                    <input type="file" name="post_image" id="post-image" accept="image/*" style="display: none;">
                    <div class="post-actions">
                        <div class="post-action" id="add-image">
                            <span class="icon icon-photo"></span>
                            Add photo
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="post-button-primary">Post</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const postModal = document.getElementById('post-modal');
        const openPostModal = document.getElementById('open-post-modal');
        const closePostModal = document.getElementById('close-post-modal');
        const addImage = document.getElementById('add-image');
        const postImage = document.getElementById('post-image');
        const imagePreview = document.getElementById('image-preview');
        
        openPostModal.addEventListener('click', () => {
            postModal.style.display = 'flex';
        });
        
        closePostModal.addEventListener('click', () => {
            postModal.style.display = 'none';
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === postModal) {
                postModal.style.display = 'none';
            }
        });
        
        addImage.addEventListener('click', () => {
            postImage.click();
        });
        
        postImage.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Connect button functionality (now sends to connections.php)
        const connectButtons = document.querySelectorAll('.connect-button');
        connectButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                this.textContent = 'Pending';
                this.disabled = true;
                
                // Send connection request via AJAX to connections.php
                fetch('connections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'receiver_id=' + userId
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data.message);
                })
                .catch(error => console.error('Fetch error:', error));
            });
        });

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
