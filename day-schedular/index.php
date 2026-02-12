<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'TaskManager.php';

requireLogin();

$auth = new Auth($pdo);
$taskManager = new TaskManager($pdo, $_SESSION['user_id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
        $result = $taskManager->addTask(
            $_POST['title'],
            $_POST['description'],
            $_POST['due_date'],
            $_POST['priority'],
            $categories
        );
        
        if ($result['success']) {
            header('Location: index.php?msg=Task added successfully');
            exit;
        }
    } elseif (isset($_POST['update_status'])) {
        $result = $taskManager->updateTask($_POST['task_id'], ['status' => $_POST['status']]);
        echo json_encode($result);
        exit;
    } elseif (isset($_POST['delete_task'])) {
        $result = $taskManager->deleteTask($_POST['task_id']);
        echo json_encode($result);
        exit;
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$date = isset($_GET['date']) ? $_GET['date'] : null;

// Get tasks and stats
$tasks = $taskManager->getTasks($filter, $date);
$categories = $taskManager->getCategories();
$stats = $taskManager->getTaskStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Task Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .task-card {
            transition: all 0.3s;
            border-left: 5px solid #6c757d;
        }
        .task-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .priority-high { border-left-color: #dc3545; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-low { border-left-color: #28a745; }
        .completed {
            opacity: 0.7;
            text-decoration: line-through;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .category-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 12px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-check2-square"></i> Daily Task Manager
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </span>
                <a href="logout.php" class="btn btn-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 col-sm-6">
                <div class="stat-card">
                    <h5>Total Tasks</h5>
                    <h2><?php echo $stats['total']; ?></h2>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h5>Completed</h5>
                    <h2><?php echo $stats['completed']; ?></h2>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <h5>Pending</h5>
                    <h2><?php echo $stats['pending']; ?></h2>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <h5>Overdue</h5>
                    <h2><?php echo $stats['overdue']; ?></h2>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <h5>Today</h5>
                    <h2><?php echo $stats['today']; ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add Task Form -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Task</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Task Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-control" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="categories" class="form-label">Categories</label>
                                <select class="form-select select2-multiple" id="categories" name="categories[]" multiple>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                style="color: <?php echo $category['color']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="add_task" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Add Task
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Categories Section -->
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-tags"></i> Categories</h5>
                    </div>
                    <div class="card-body">
                        <form id="addCategoryForm">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="category_name" placeholder="Category name">
                                <input type="color" class="form-control form-control-color" id="category_color" value="#6c757d">
                                <button type="submit" class="btn btn-success">Add</button>
                            </div>
                        </form>
                        <div id="categoriesList">
                            <?php foreach ($categories as $category): ?>
                                <span class="category-badge" style="background-color: <?php echo $category['color']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-task"></i> Tasks</h5>
                        <div>
                            <select class="form-select form-select-sm" id="filterTasks" style="width: auto;">
                                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Tasks</option>
                                <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No tasks found. Add a new task to get started!
                            </div>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <div class="card task-card mb-3 priority-<?php echo $task['priority']; ?> 
                                          <?php echo $task['status'] == 'completed' ? 'completed' : ''; ?>"
                                     data-task-id="<?php echo $task['id']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                                
                                                <?php if (!empty($task['category_names'])): ?>
                                                    <div class="mb-2">
                                                        <?php 
                                                        $names = explode(',', $task['category_names']);
                                                        $colors = explode(',', $task['category_colors']);
                                                        for ($i = 0; $i < count($names); $i++): 
                                                        ?>
                                                            <span class="category-badge" style="background-color: <?php echo $colors[$i]; ?>">
                                                                <?php echo htmlspecialchars($names[$i]); ?>
                                                            </span>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-2">
                                                    <span class="badge bg-<?php 
                                                        echo $task['priority'] == 'high' ? 'danger' : 
                                                            ($task['priority'] == 'medium' ? 'warning' : 'success'); 
                                                    ?>">
                                                        <i class="bi bi-flag"></i> <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                    <span class="badge bg-<?php 
                                                        echo $task['status'] == 'completed' ? 'success' : 
                                                            ($task['status'] == 'in_progress' ? 'info' : 'secondary'); 
                                                    ?>">
                                                        <i class="bi bi-<?php 
                                                            echo $task['status'] == 'completed' ? 'check-circle' : 
                                                                ($task['status'] == 'in_progress' ? 'arrow-repeat' : 'hourglass'); 
                                                        ?>"></i> 
                                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                    </span>
                                                    <span class="badge bg-<?php 
                                                        echo strtotime($task['due_date']) < strtotime(date('Y-m-d')) && 
                                                             $task['status'] != 'completed' ? 'danger' : 'dark'; 
                                                    ?>">
                                                        <i class="bi bi-calendar"></i> 
                                                        <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                        data-bs-toggle="dropdown">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item update-status" href="#" data-status="pending">
                                                            <i class="bi bi-hourglass"></i> Pending
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item update-status" href="#" data-status="in_progress">
                                                            <i class="bi bi-arrow-repeat"></i> In Progress
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item update-status" href="#" data-status="completed">
                                                            <i class="bi bi-check-circle"></i> Completed
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger delete-task" href="#">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                placeholder: 'Select categories',
                width: '100%'
            });

            // Filter tasks
            $('#filterTasks').change(function() {
                let filter = $(this).val();
                window.location.href = 'index.php?filter=' + filter;
            });

            // Update task status
            $('.update-status').click(function(e) {
                e.preventDefault();
                let taskCard = $(this).closest('.task-card');
                let taskId = taskCard.data('task-id');
                let status = $(this).data('status');
                
                $.ajax({
                    url: 'index.php',
                    method: 'POST',
                    data: {
                        update_status: true,
                        task_id: taskId,
                        status: status
                    },
                    success: function(response) {
                        location.reload();
                    }
                });
            });

            // Delete task
            $('.delete-task').click(function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this task?')) {
                    let taskCard = $(this).closest('.task-card');
                    let taskId = taskCard.data('task-id');
                    
                    $.ajax({
                        url: 'index.php',
                        method: 'POST',
                        data: {
                            delete_task: true,
                            task_id: taskId
                        },
                        success: function(response) {
                            location.reload();
                        }
                    });
                }
            });

            // Add category via AJAX
            $('#addCategoryForm').submit(function(e) {
                e.preventDefault();
                let name = $('#category_name').val();
                let color = $('#category_color').val();
                
                if (name.trim() === '') {
                    alert('Please enter a category name');
                    return;
                }
                
                $.ajax({
                    url: 'add_category.php',
                    method: 'POST',
                    data: {
                        name: name,
                        color: color
                    },
                    success: function(response) {
                        location.reload();
                    }
                });
            });
        });
    </script>
</body>
</html>