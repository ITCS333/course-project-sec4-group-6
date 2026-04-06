<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? null;
$order = $_GET['order'] ?? null;


function getUsers($db) {
 $query = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $sort   = isset($_GET['sort']) ? trim($_GET['sort']) : null;
    $order  = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'asc';

    if (!empty($search)) {
        $query .= " WHERE name LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    $allowedSortFields = ['name', 'email', 'is_admin'];
    if ($sort && in_array($sort, $allowedSortFields, true)) {
        $direction = ($order === 'desc') ? 'DESC' : 'ASC';
        $query .= " ORDER BY $sort $direction";
    }

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users, 200);
}


function getUserById($db, $id) {
     $query = "SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(["message" => "User not found"], 404);
        return;
    }

    sendResponse($user, 200);

}


function createUser($db, $data) {

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse(["message" => "Missing required fields"], 400);
        return;
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = trim($data['password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(["message" => "Invalid email format"], 400);
        return;
    }

    if (strlen($password) < 8) {
        sendResponse(["message" => "Password must be at least 8 characters"], 400);
        return;
    }

    $checkQuery = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($checkQuery);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    if ($stmt->fetch()) {
        sendResponse(["message" => "Email already exists"], 409);
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $is_admin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;

    $query = "INSERT INTO users (name, email, password, is_admin) 
              VALUES (:name, :email, :password, :is_admin)";

    $stmt = $db->prepare($query);

    $success = $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':is_admin' => $is_admin
    ]);

    if ($success) {
        $newId = $db->lastInsertId();
        sendResponse(["id" => $newId], 201);
    } else {
        sendResponse(["message" => "Failed to create user"], 500);
    }
}


function updateUser($db, $data) {
     if (!isset($data['id'])) {
        sendResponse(["message" => "User id is required"], 400);
        return;
    }

    $id = (int) $data['id'];

    $checkStmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(["message" => "User not found"], 404);
        return;
    }

    $fields = [];
    $params = [':id' => $id];

    if (isset($data['name'])) {
        $name = trim($data['name']);
        $fields[] = "name = :name";
        $params[':name'] = $name;
    }

    if (isset($data['email'])) {
        $email = trim($data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(["message" => "Invalid email format"], 400);
            return;
        }

        $emailCheckStmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $emailCheckStmt->bindValue(':email', $email);
        $emailCheckStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $emailCheckStmt->execute();

        if ($emailCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            sendResponse(["message" => "Email already exists"], 409);
            return;
        }

        $fields[] = "email = :email";
        $params[':email'] = $email;
    }

    if (isset($data['is_admin'])) {
        $isAdmin = (int) $data['is_admin'];

        if ($isAdmin !== 0 && $isAdmin !== 1) {
            sendResponse(["message" => "is_admin must be 0 or 1"], 400);
            return;
        }

        $fields[] = "is_admin = :is_admin";
        $params[':is_admin'] = $isAdmin;
    }

    if (empty($fields)) {
        sendResponse(["message" => "No fields provided for update"], 400);
        return;
    }

    $query = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    if ($stmt->execute($params)) {
        sendResponse(["message" => "User updated successfully"], 200);
    } else {
        sendResponse(["message" => "Failed to update user"], 500);
    }
}


function deleteUser($db, $id) {
        if (!$id) {
        sendResponse(["message" => "User id is required"], 400);
        return;
    }

    $checkStmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(["message" => "User not found"], 404);
        return;
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        sendResponse(["message" => "User deleted successfully"], 200);
    } else {
        sendResponse(["message" => "Failed to delete user"], 500);
    }
}


function changePassword($db, $data) {
     if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse(["message" => "Missing required fields"], 400);
        return;
    }

    $id = (int) $data['id'];
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];

    if (strlen($newPassword) < 8) {
        sendResponse(["message" => "New password must be at least 8 characters"], 400);
        return;
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(["message" => "User not found"], 404);
        return;
    }

    if (!password_verify($currentPassword, $user['password'])) {
        sendResponse(["message" => "Current password is incorrect"], 401);
        return;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $updateStmt->bindValue(':password', $hashedPassword);
    $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        sendResponse(["message" => "Password updated successfully"], 200);
    } else {
        sendResponse(["message" => "Failed to update password"], 500);
    }
}


try {

    if ($method === 'GET') {
        if (!empty($id)) {
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
        sendResponse(["message" => "Method Not Allowed"], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(["message" => "Database error"], 500);

} catch (Exception $e) {
    sendResponse(["message" => $e->getMessage()], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sends a JSON response and terminates execution.
 *
 * @param mixed $data       Data to include in the response.
 *                          On success, pass the payload directly.
 *                          On error, pass a string message.
 * @param int   $statusCode HTTP status code (default 200).
 */
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


/**
 * Validates an email address.
 *
 * @param  string $email
 * @return bool   True if the email passes FILTER_VALIDATE_EMAIL, false otherwise.
 */
function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}


/**
 * Sanitizes a string input value.
 * Use this before inserting user-supplied strings into the database.
 *
 * @param  string $data
 * @return string Trimmed, tag-stripped, and HTML-escaped string.
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

?>
