<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'TaskManager.php';

requireLogin();

$taskManager = new TaskManager($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $taskManager->addCategory($_POST['name'], $_POST['color']);
    echo json_encode($result);
    exit;
}
?>