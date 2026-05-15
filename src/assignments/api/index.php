<?php
session_start();
$_SESSION['api'] = 'assignments';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true) ?? [];

$action       = $_GET['action'] ?? null;
$id           = $_GET['id'] ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id'] ?? null;

function getAllAssignments(PDO $db): void {
    $query = "SELECT * FROM assignments";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = "%" . $_GET['search'] . "%";
    }

    $allowedSort = ['title', 'due_date', 'created_at'];
    $sort = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'due_date';
    $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

    $query .= " ORDER BY $sort $order";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $rows]);
}

function getAssignmentById(PDO $db, $id): void {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
        sendResponse(['success' => true, 'data' => $row]);
    } else {
        sendResponse(['success' => false, 'message' => 'Not found'], 404);
    }
}

function createAssignment(PDO $db, array $data): void {
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $due_date = $data['due_date'];

    if (!validateDate($due_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date'], 400);
    }

    $files = isset($data['files']) && is_array($data['files']) ? json_encode($data['files']) : json_encode([]);

    $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $description, $due_date, $files]);

    if ($stmt->rowCount()) {
        sendResponse(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    }
    sendResponse(['success' => false], 500);
}

function updateAssignment(PDO $db, array $data): void {
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'ID required'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Not found'], 404);
    }

    $fields = [];
    $values = [];

    if (!empty($data['title'])) { $fields[] = "title=?"; $values[] = sanitizeInput($data['title']); }
    if (!empty($data['description'])) { $fields[] = "description=?"; $values[] = sanitizeInput($data['description']); }
    if (!empty($data['due_date'])) {
        if (!validateDate($data['due_date'])) sendResponse(['success' => false, 'message' => 'Invalid date'], 400);
        $fields[] = "due_date=?"; $values[] = $data['due_date'];
    }
    if (isset($data['files'])) { $fields[] = "files=?"; $values[] = json_encode($data['files']); }

    if (empty($fields)) sendResponse(['success' => false, 'message' => 'No fields to update'], 400);

    $values[] = $data['id'];
    $sql = "UPDATE assignments SET " . implode(",", $fields) . " WHERE id=?";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($values)) sendResponse(['success' => true]);
    sendResponse(['success' => false], 500);
}

function deleteAssignment(PDO $db, $id): void {
    if (!$id || !is_numeric($id)) sendResponse(['success' => false], 400);
    $stmt = $db->prepare("DELETE FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount()) sendResponse(['success' => true]);
    sendResponse(['success' => false], 404);
}

function getCommentsByAssignment(PDO $db, $assignmentId): void {
    if (!$assignmentId || !is_numeric($assignmentId)) sendResponse(['success' => false], 400);
    $check = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $check->execute([$assignmentId]);
    if (!$check->fetch()) sendResponse(['success' => false], 404);

    $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id=? ORDER BY created_at ASC");
    $stmt->execute([$assignmentId]);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data): void {
    if (empty($data['assignment_id']) || empty($data['author']) || empty($data['text'])) sendResponse(['success' => false], 400);
    $check = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $check->execute([$data['assignment_id']]);
    if (!$check->fetch()) sendResponse(['success' => false], 404);

    $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data['assignment_id'], sanitizeInput($data['author']), sanitizeInput($data['text'])]);

    if ($stmt->rowCount()) {
        sendResponse([
            'success' => true,
            'data' => [
                'id' => (int)$db->lastInsertId(),
                'assignment_id' => (int)$data['assignment_id'],
                'author' => $data['author'],
                'text' => $data['text']
            ]
        ], 201);
    }
    sendResponse(['success' => false], 500);
}

function deleteComment(PDO $db, $commentId): void {
    if (!$commentId || !is_numeric($commentId)) sendResponse(['success' => false], 400);
    $check = $db->prepare("SELECT id FROM comments_assignment WHERE id = ?");
    $check->execute([$commentId]);
    if (!$check->fetch()) sendResponse(['success' => false], 404);

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id=?");
    $stmt->execute([$commentId]);
    if ($stmt->rowCount()) sendResponse(['success' => true]);
    sendResponse(['success' => false], 500);
}

try {
    if ($method === 'GET') {
        if ($action === 'comments') getCommentsByAssignment($db, $assignmentId);
        elseif ($id) getAssignmentById($db, $id);
        else getAllAssignments($db);
    } elseif ($method === 'POST') {
        if ($action === 'comment') createComment($db, $data);
        else createAssignment($db, $data);
    } elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') deleteComment($db, $commentId);
        else deleteAssignment($db, $id);
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

function sendResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>
