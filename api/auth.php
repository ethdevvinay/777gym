<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($email) || empty($password)) {
        jsonResponse(false, [], 'Email and Password are required', 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        jsonResponse(true, [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ], 'Login successful');
    } else {
        jsonResponse(false, [], 'Invalid credentials', 401);
    }
} elseif ($action === 'logout') {
    session_destroy();
    jsonResponse(true, [], 'Logged out');
} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
