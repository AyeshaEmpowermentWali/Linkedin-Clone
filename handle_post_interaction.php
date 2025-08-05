<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';
    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    if ($postId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID.']);
        exit;
    }

    if ($action === 'like_post') {
        // Check if user has already liked this post
        $stmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$userId, $postId]);
        $alreadyLiked = $stmt->fetchColumn();

        if ($alreadyLiked) {
            // User already liked, so unlike it
            $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$userId, $postId]);
            $message = 'Post unliked.';
            $liked = false;
        } else {
            // User has not liked, so like it
            $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
            $stmt->execute([$userId, $postId]);
            $message = 'Post liked.';
            $liked = true;
        }

        // Get updated like count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
        $stmt->execute([$postId]);
        $likeCount = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'message' => $message, 'liked' => $liked, 'like_count' => $likeCount]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
?>
