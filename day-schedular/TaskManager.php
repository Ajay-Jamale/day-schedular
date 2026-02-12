<?php
require_once 'config.php';

class TaskManager {
    private $pdo;
    private $userId;
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    public function addTask($title, $description, $dueDate, $priority, $categories = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Insert task
            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (user_id, title, description, due_date, priority, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([$this->userId, $title, $description, $dueDate, $priority]);
            $taskId = $this->pdo->lastInsertId();
            
            // Add categories
            if (!empty($categories)) {
                $stmt = $this->pdo->prepare("INSERT INTO task_categories (task_id, category_id) VALUES (?, ?)");
                foreach ($categories as $categoryId) {
                    $stmt->execute([$taskId, $categoryId]);
                }
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Task added successfully', 'task_id' => $taskId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to add task: ' . $e->getMessage()];
        }
    }
    
    public function getTasks($filter = 'all', $date = null) {
        $query = "SELECT t.*, 
                         GROUP_CONCAT(c.name) as category_names,
                         GROUP_CONCAT(c.color) as category_colors,
                         GROUP_CONCAT(c.id) as category_ids
                  FROM tasks t 
                  LEFT JOIN task_categories tc ON t.id = tc.task_id
                  LEFT JOIN categories c ON tc.category_id = c.id
                  WHERE t.user_id = ?";
        
        $params = [$this->userId];
        
        // Apply filters
        if ($filter === 'pending') {
            $query .= " AND t.status = 'pending'";
        } elseif ($filter === 'in_progress') {
            $query .= " AND t.status = 'in_progress'";
        } elseif ($filter === 'completed') {
            $query .= " AND t.status = 'completed'";
        } elseif ($filter === 'today' || $date) {
            $date = $date ?: date('Y-m-d');
            $query .= " AND t.due_date = ?";
            $params[] = $date;
        }
        
        $query .= " GROUP BY t.id ORDER BY 
                    CASE t.priority 
                        WHEN 'high' THEN 1 
                        WHEN 'medium' THEN 2 
                        WHEN 'low' THEN 3 
                    END, 
                    t.due_date ASC, 
                    t.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function updateTask($taskId, $data) {
        $allowedFields = ['title', 'description', 'status', 'priority', 'due_date'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }
        
        $params[] = $taskId;
        $params[] = $this->userId;
        
        $query = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($query);
        
        if ($stmt->execute($params)) {
            return ['success' => true, 'message' => 'Task updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update task'];
    }
    
    public function deleteTask($taskId) {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        
        if ($stmt->execute([$taskId, $this->userId])) {
            return ['success' => true, 'message' => 'Task deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete task'];
    }
    
    public function getCategories() {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }
    
    public function addCategory($name, $color) {
        $stmt = $this->pdo->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$this->userId, $name, $color])) {
            return ['success' => true, 'message' => 'Category added successfully', 'id' => $this->pdo->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to add category'];
    }
    
    public function getTaskStats() {
        $stats = [];
        
        // Total tasks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $stats['total'] = $stmt->fetchColumn();
        
        // Completed tasks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'");
        $stmt->execute([$this->userId]);
        $stats['completed'] = $stmt->fetchColumn();
        
        // Pending tasks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$this->userId]);
        $stats['pending'] = $stmt->fetchColumn();
        
        // Overdue tasks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND due_date < CURDATE() AND status != 'completed'");
        $stmt->execute([$this->userId]);
        $stats['overdue'] = $stmt->fetchColumn();
        
        // Today's tasks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND due_date = CURDATE()");
        $stmt->execute([$this->userId]);
        $stats['today'] = $stmt->fetchColumn();
        
        return $stats;
    }
}
?>