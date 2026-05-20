<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!function_exists('getDBConnection')) {
    $paths = [
        __DIR__ . '/../../common/db.php',
        __DIR__ . '/../../../db.php',
        __DIR__ . '/../../db.php',
        __DIR__ . '/../../../config.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);

    if ($statusCode < 400) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $data
        ]);
    }

    exit;
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function getUsers($db) {
    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params['search'] = "%" . $_GET['search'] . "%";
    }

    $allowedSort = ['name', 'email', 'is_admin'];
    if (!empty($_GET['sort']) && in_array($_GET['sort'], $allowedSort)) {
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY " . $_GET['sort'] . " " . $order;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users, 200);
}

function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user, 200);
}

function createUser($db, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Missing required fields", 400);
    }

    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $password = trim($data['password']);
    $is_admin = isset($data['is_admin']) ? (int) $data['is_admin'] : 0;

    if (!validateEmail($email)) {
        sendResponse("Invalid email format", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    if (!in_array($is_admin, [0, 1])) {
        $is_admin = 0;
    }

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute(['email' => $email]);

    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password, is_admin)
         VALUES (:name, :email, :password, :is_admin)"
    );

    $ok = $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'is_admin' => $is_admin
    ]);

    if (!$ok) {
        sendResponse("Failed to create user", 500);
    }

    sendResponse([
        'id' => $db->lastInsertId(),
        'name' => $name,
        'email' => $email,
        'is_admin' => $is_admin
    ], 201);
}

function updateUser($db, $data) {
    if (empty($data['id'])) {
        sendResponse("User id is required", 400);
    }

    $id = (int) $data['id'];

    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        sendResponse("User not found", 404);
    }

    $fields = [];
    $params = ['id' => $id];

    if (isset($data['name'])) {
        $fields[] = "name = :name";
        $params['name'] = sanitizeInput($data['name']);
    }

    if (isset($data['email'])) {
        $email = sanitizeInput($data['email']);

        if (!validateEmail($email)) {
            sendResponse("Invalid email format", 400);
        }

        $dup = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $dup->execute([
            'email' => $email,
            'id' => $id
        ]);

        if ($dup->fetch()) {
            sendResponse("Email already exists", 409);
        }

        $fields[] = "email = :email";
        $params['email'] = $email;
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin = :is_admin";
        $params['is_admin'] = in_array((int)$data['is_admin'], [0, 1]) ? (int)$data['is_admin'] : 0;
    }

    if (empty($fields)) {
        sendResponse("No fields to update", 200);
    }

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse("User updated successfully", 200);
}

function deleteUser($db, $id) {
    if (!$id) {
        sendResponse("User id is required", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        sendResponse("User not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);

    sendResponse("User deleted successfully", 200);
}

function changePassword($db, $data) {
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse("Missing required fields", 400);
    }

    $id = (int) $data['id'];
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];

    if (strlen($newPassword) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($currentPassword, $user['password'])) {
        sendResponse("Invalid current password", 401);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $update = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $update->execute([
        'password' => $hashedPassword,
        'id' => $id
    ]);

    sendResponse("Password updated successfully", 200);
}

try {
    if ($method === 'GET') {
        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateUser($db, $data);
    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);
    } else {
        sendResponse("Method not allowed", 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error", 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>