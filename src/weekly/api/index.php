<?php
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by title LIKE or description LIKE
 *   sort   — allowed: title, start_date   (default: start_date)
 *   order  — allowed: asc, desc           (default: asc)
 *
 * Each week row in the response has links decoded from its JSON string
 * to a PHP array before encoding the final JSON output.
 */
function getAllWeeks(PDO $db): void
{
   $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";

    $search = $_GET['search'] ?? '';

    if (!empty($search)) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $allowedSort = ['title', 'start_date'];
    $sort = $_GET['sort'] ?? 'start_date';

    if (!in_array($sort, $allowedSort)) {
        $sort = 'start_date';
    }

    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'asc');

    if (!in_array($order, $allowedOrder)) {
        $order = 'asc';
    }

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    $stmt->execute();

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$row) {
        $row['links'] = json_decode($row['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}


/**
 * Get a single week by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, title, start_date, description,
 *                                 links, created_at } }
 * Response (not found): HTTP 404.
 */
function getWeekById(PDO $db, $id): void
{
     if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid week ID'
        ], 400);
    }

    $query = "SELECT id, title, start_date, description, links, created_at 
              FROM weeks 
              WHERE id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$id]);

    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];

        sendResponse([
            'success' => true,
            'data' => $week
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Week not found'
        ], 404);
    }
}


/**
 * Create a new week.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   title       — string (required)
 *   start_date  — string "YYYY-MM-DD" (required)
 *   description — string (optional, defaults to "")
 *   links       — array of URL strings (optional, defaults to [])
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (invalid start_date): HTTP 400.
 */
function createWeek(PDO $db, array $data): void
{
    if (
        empty($data['title']) ||
        empty($data['start_date'])
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Title and start date are required'
        ], 400);
    }

    $title = trim($data['title']);
    $start_date = trim($data['start_date']);
    $description = trim($data['description'] ?? '');

    $date = DateTime::createFromFormat('Y-m-d', $start_date);

    if (!$date || $date->format('Y-m-d') !== $start_date) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid start date format'
        ], 400);
    }

    $links = [];

    if (isset($data['links']) && is_array($data['links'])) {
        $links = $data['links'];
    }

    $linksJson = json_encode($links);

    $query = "INSERT INTO weeks (title, start_date, description, links)
              VALUES (?, ?, ?, ?)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        $title,
        $start_date,
        $description,
        $linksJson
    ]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully',
            'id' => $db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create week'
        ], 500);
    }
}


/**
 * Update an existing week.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the week to update (required).
 * Optional JSON body fields (at least one must be present):
 *   title, start_date, description, links.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 * Response (invalid start_date): HTTP 400.
 */
function updateWeek(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid week ID'
        ], 400);
    }

    $id = $data['id'];

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found'
        ], 404);
    }

    $clauses = [];
    $values = [];

    if (isset($data['title'])) {
        $clauses[] = "title = ?";
        $values[] = trim($data['title']);
    }

    if (isset($data['start_date'])) {
        $start_date = trim($data['start_date']);

        $date = DateTime::createFromFormat('Y-m-d', $start_date);

        if (!$date || $date->format('Y-m-d') !== $start_date) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid start date format'
            ], 400);
        }

        $clauses[] = "start_date = ?";
        $values[] = $start_date;
    }

    if (isset($data['description'])) {
        $clauses[] = "description = ?";
        $values[] = trim($data['description']);
    }

    if (isset($data['links'])) {
        $clauses[] = "links = ?";

        if (is_array($data['links'])) {
            $values[] = json_encode($data['links']);
        } else {
            $values[] = json_encode([]);
        }
    }

    if (empty($clauses)) {
        sendResponse([
            'success' => false,
            'message' => 'No fields to update'
        ], 400);
    }

    $query = "UPDATE weeks SET " . implode(', ', $clauses) . " WHERE id = ?";

    $values[] = $id;

    $stmt = $db->prepare($query);
    $stmt->execute($values);

    if ($stmt->rowCount() >= 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week updated successfully'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to update week'
        ], 500);
    }
}


/**
 * Delete a week by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on comments_week.week_id
 * automatically removes all comments for this week — no manual
 * deletion of comments is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteWeek(PDO $db, $id): void
{
 if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid week ID'
        ], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week deleted successfully'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete week'
        ], 500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 * Method: GET with ?action=comments&week_id={id}.
 *
 * Reads from the comments_week table.
 * Returns an empty data array if no comments exist — not an error.
 *
 * Each comment object: { id, week_id, author, text, created_at }
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
 if (!$weekId || !is_numeric($weekId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid week ID'
        ], 400);
    }

    $query = "SELECT id, week_id, author, text, created_at
              FROM comments_week
              WHERE week_id = ?
              ORDER BY created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([$weekId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}


/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 *
 * Required JSON body:
 *   week_id — integer FK into weeks.id (required)
 *   author  — string (required)
 *   text    — string (required, must be non-empty after trim)
 *
 * Response (success): HTTP 201 — { success, message, id, data: comment }
 * Response (week not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 */
function createComment(PDO $db, array $data): void
{
 
    if (
        empty($data['week_id']) ||
        empty(trim($data['author'] ?? '')) ||
        empty(trim($data['text'] ?? ''))
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Week ID, author, and text are required'
        ], 400);
    }

    if (!is_numeric($data['week_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid week ID'
        ], 400);
    }

    $weekId = $data['week_id'];
    $author = trim($data['author']);
    $text = trim($data['text']);

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$weekId]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found'
        ], 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_week (week_id, author, text)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $commentId = $db->lastInsertId();

        $comment = [
            'id' => $commentId,
            'week_id' => $weekId,
            'author' => $author,
            'text' => $text
        ];

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully',
            'id' => $commentId,
            'data' => $comment
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create comment'
        ], 500);
    }
}


/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID'
        ], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $checkStmt->execute([$commentId]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found'
        ], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment'
        ], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
  if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);

        } elseif ($id !== null) {
            getWeekById($db, $id);

        } else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);

        } else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);

        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed'
        ], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Database error'
    ], 500);

} catch (Exception $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Server error'
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
   http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}


/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
   $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
