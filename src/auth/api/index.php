<?php
/**
 * Authentication Handler for Login Form
 * 
 * This PHP script handles user authentication via POST requests from the Fetch API.
 * It validates credentials against a MySQL database using PDO,
 * creates sessions, and returns JSON responses.
 */

// --- Session Management ---
session_start();

// --- Set Response Headers ---
header('Content-Type: application/json');

// Optional CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit();
}

// --- Get POST Data ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// --- Extract the email and password ---
if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit();
}

// --- Store the email and password in variables ---
$email = trim($data['email']);
$password = $data['password'];

// --- Server-Side Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit();
}

if (strlen($password) < 8) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters'
    ]);
    exit();
}

// --- Database Connection ---
$db = getDBConnection();

// --- Database Operations ---
try {
    // --- Prepare SQL Query ---
    $query = "SELECT id, name, email, password, is_admin FROM users WHERE email = :email";

    // --- Prepare the Statement ---
    $stmt = $db->prepare($query);

    // --- Execute the Query ---
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    // --- Fetch User Data ---
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User Exists and Password Matches ---
    if ($user && password_verify($password, $user['password'])) {

        // --- Handle Successful Authentication ---
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['logged_in'] = true;

        $response = [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin']
            ]
        ];

        echo json_encode($response);
        exit();
    }

    // --- Handle Failed Authentication ---
    $response = [
        'success' => false,
        'message' => 'Invalid email or password'
    ];

    echo json_encode($response);
    exit();

} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log($e->getMessage());

    // Return a generic error to the client
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);

    exit();
}

// --- End of Script ---
?>