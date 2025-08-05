<?php
require_once 'db.php';

// This script creates a test user with known credentials
// Email: admin@test.com
// Password: admin123

$name = "Admin User";
$email = "admin@test.com";
$password = "admin123";
$headline = "System Administrator";

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo "User already exists!<br>";
    } else {
        // Create the user
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, headline, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $hashed_password, $headline]);
        
        echo "Test user created successfully!<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password . "<br>";
        echo "<a href='login.php'>Go to Login</a>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
