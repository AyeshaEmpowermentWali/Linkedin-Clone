<?php
// Database connection
$host = "localhost"; // Update if your host is different
$dbname = "dbxexmgc5fpukt";
$username = "u2fm1vryymcjr";
$password = "mfd4eyv5w7bk";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Session management
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserData($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getProfilePicture($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    // Use a base64 encoded SVG as a default avatar if no profile_pic is set or it's the old default string
    $defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHZpZXdCb3g9JzAgMCAyNCAyNCcgZmlsbD0nI2NjY2NjYyc+PHBhdGggZD0nTTExLjUgMTJjMi4yMSAwIDQtMS43OSA0LTRzLTEuNzktNC00LTRzLTMuOTkgMS43OS0zLjk5IDQgMS43OCAzLjk5IDMuOTkgMy45OXptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyLjAxaDE2VjE2YzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg==';
    return ($result['profile_pic'] && $result['profile_pic'] !== 'default-avatar.png') ? $result['profile_pic'] : $defaultAvatar;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600); // Fixed: should be hours, not days
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = round($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } elseif ($diff < 31536000) {
        $months = round($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = round($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}
?>
