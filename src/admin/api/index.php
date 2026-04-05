<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 *
 * Database Table (ground truth: see schema.sql):
 * Table: users
 * Columns:
 *   - id         (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - name       (VARCHAR(100), NOT NULL)
 *   - email      (VARCHAR(100), NOT NULL, UNIQUE)
 *   - password   (VARCHAR(255), NOT NULL) - bcrypt hash
 *   - is_admin   (TINYINT(1), NOT NULL, DEFAULT 0)
 *   - created_at (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
 *
 * HTTP Methods Supported:
 *   - GET    : Retrieve all users (with optional search/sort query params)
 *   - GET    : Retrieve a single user by id (?id=1)
 *   - POST   : Create a new user
 *   - POST   : Change a user's password (?action=change_password)
 *   - PUT    : Update an existing user's name, email, or is_admin
 *   - DELETE : Delete a user by id (?id=1)
 *
 * Response Format: JSON
 * All responses have the shape:
 *   { "success": true,  "data": ... }
 *   { "success": false, "message": "..." }
 */


// TODO: Set headers for JSON response and CORS.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// TODO: Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// TODO: Include the database connection file.
// Assume a function getDBConnection() is available that returns a PDO instance
require_once '../db.php';

$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);


// TODO: Read query string parameters.
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? null;
$order = $_GET['order'] ?? null;


/**
 * Function: Get all users, or search/filter users.
 * Method: GET (no ?id parameter)
 *
 * Supported query parameters:
 *   - search (string) : filters rows where name LIKE or email LIKE the term
 *   - sort   (string) : column to sort by; allowed values: name, email, is_admin
 *   - order  (string) : sort direction; allowed values: asc, desc (default: asc)
 *
 * Notes:
 *   - Never return the password column in the response.
 *   - Validate the 'sort' value against the whitelist (name, email, is_admin)
 *     to prevent SQL injection before interpolating it into the ORDER BY clause.
 *   - Validate the 'order' value; only accept 'asc' or 'desc'.
 */
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


/**
 * Function: Get a single user by primary key.
 * Method: GET with ?id=<int>
 *
 * Query parameters:
 *   - id (int, required) : the user's primary key in the users table
 */
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


/**
 * Function: Create a new user.
 * Method: POST (no ?action parameter)
 *
 * Expected JSON body:
 *   - name     (string, required)
 *   - email    (string, required) - must be a valid email address and unique
 *   - password (string, required) - plaintext; will be hashed before storage
 *   - is_admin (int, optional)    - 0 (student) or 1 (admin); defaults to 0
 */
function createUser($db, $data) {
     // 1. Validate required fields
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse(["message" => "Missing required fields"], 400);
        return;
    }

    // 2. Trim + validate email
    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = trim($data['password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(["message" => "Invalid email format"], 400);
        return;
    }

    // 3. Validate password length
    if (strlen($password) < 8) {
        sendResponse(["message" => "Password must be at least 8 characters"], 400);
        return;
    }

    // 4. Check duplicate email
    $checkQuery = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($checkQuery);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    if ($stmt->fetch()) {
        sendResponse(["message" => "Email already exists"], 409);
        return;
    }

    // 5. Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 6. Handle is_admin
    $is_admin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;

    // 7. Insert user
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


/**
 * Function: Update an existing user.
 * Method: PUT
 *
 * Expected JSON body:
 *   - id       (int, required)    : primary key of the user to update
 *   - name     (string, optional) : new name
 *   - email    (string, optional) : new email (must remain unique)
 *   - is_admin (int, optional)    : 0 or 1
 *
 * Note: password changes are handled by the separate changePassword endpoint.
 */
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


/**
 * Function: Delete a user by primary key.
 * Method: DELETE
 *
 * Query parameter:
 *   - id (int, required) : primary key of the user to delete
 */
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


/**
 * Function: Change a user's password.
 * Method: POST with ?action=change_password
 *
 * Expected JSON body:
 *   - id               (int, required)    : primary key of the user whose password is changing
 *   - current_password (string, required) : must match the stored bcrypt hash
 *   - new_password     (string, required) : plaintext; will be hashed before storage
 */
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


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

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
