<?php
require_once 'config.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($username, $email, $password) {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$username, $email, $hashedPassword])) {
            $userId = $this->pdo->lastInsertId();
            
            // Create default categories
            $this->createDefaultCategories($userId);
            
            return ['success' => true, 'message' => 'Registration successful'];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
    }
    
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return ['success' => true, 'message' => 'Login successful'];
        }
        
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    private function createDefaultCategories($userId) {
        $defaultCategories = [
            ['Work', '#007bff'],
            ['Personal', '#28a745'],
            ['Shopping', '#ffc107'],
            ['Health', '#dc3545'],
            ['Education', '#6f42c1']
        ];
        
        $stmt = $this->pdo->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
        
        foreach ($defaultCategories as $category) {
            $stmt->execute([$userId, $category[0], $category[1]]);
        }
    }
}
?>