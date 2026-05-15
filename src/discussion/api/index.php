<?php
/**
 * Discussion Board API
 *
 * RESTful API for CRUD operations on discussion topics and their replies.
 * Uses PDO to interact with the MySQL database defined in schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: topics
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   subject    VARCHAR(255)  NOT NULL
 *   message    TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * Table: replies
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   topic_id   INT UNSIGNED  NOT NULL — FK → topics.id (ON DELETE CASCADE)
 *   text       TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve topic(s) or replies
 *   POST   — Create a new topic or reply
 *   PUT    — Update an existing topic
 *   DELETE — Delete a topic (cascade removes its replies) or a reply
 *
 * URL scheme (all requests go to index.php):
 *
 *   Topics:
 *     GET    ./api/index.php                  — list all topics
 *     GET    ./api/index.php?id={id}           — get one topic by integer id
 *     POST   ./api/index.php                  — create a new topic
 *     PUT    ./api/index.php                  — update a topic (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a topic
 *
 *   Replies (action parameter selects the replies sub-resource):
 *     GET    ./api/index.php?action=replies&topic_id={id}
 *                                             — list replies for a topic
 *     POST   ./api/index.php?action=reply     — create a reply
 *     DELETE ./api/index.php?action=delete_reply&id={id}
 *                                             — delete a single reply
 *
 * Query parameters for GET all topics:
 *   search — filter rows where subject LIKE or message LIKE or author LIKE
 *   sort   — column to sort by; allowed: subject, author, created_at
 *            (default: created_at)
 *   order  — sort direction; allowed: asc, desc (default: desc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS.
header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the shared database connection file.
require_once __DIR__ . '/../../common/db.php';


// TODO: Get the PDO database connection.
$db = getDBConnection();


// TODO: Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];


// TODO: Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];


// TODO: Read query parameters.
$action  = $_GET['action']   ?? null;  
$id      = $_GET['id']       ?? null;  
$topicId = $_GET['topic_id'] ?? null;


// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

/**
 * Get all topics (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by subject LIKE or message LIKE or author LIKE
 *   sort   — allowed: subject, author, created_at   (default: created_at)
 *   order  — allowed: asc, desc                     (default: desc)
 */
function getAllTopics(PDO $db): void
{
    // Build the base SELECT query.
    $query = "SELECT id, subject, message, author, created_at FROM topics";

    // Check if search exists.
    $search = $_GET['search'] ?? '';

    if (!empty($search)) {
        $query .= " WHERE subject LIKE :search 
                    OR message LIKE :search 
                    OR author LIKE :search";
    }

    // Validate sort column.
    $allowedSort = ['subject', 'author', 'created_at'];

    $sort = $_GET['sort'] ?? 'created_at';

    if (!in_array($sort, $allowedSort)) {
        $sort = 'created_at';
    }

    // Validate order direction.
    $allowedOrder = ['asc', 'desc'];

    $order = strtolower($_GET['order'] ?? 'desc');

    if (!in_array($order, $allowedOrder)) {
        $order = 'desc';
    }

    // Append ORDER BY.
    $query .= " ORDER BY $sort $order";

    // Prepare statement.
    $stmt = $db->prepare($query);

    // Bind search parameter if exists.
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    // Execute query.
    $stmt->execute();

    // Fetch all rows.
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send response.
    sendResponse([
        'success' => true,
        'data' => $topics
    ]);
}


/**
 * Get a single topic by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, subject, message, author, created_at } }
 * Response (not found): HTTP 404.
 */
function getTopicById(PDO $db, $id): void
{
    // Validate id.
    if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid topic id'
        ], 400);
    }

    // Select topic by id.
    $query = "SELECT id, subject, message, author, created_at
              FROM topics
              WHERE id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$id]);

    // Fetch one row.
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if found.
    if ($topic) {
        sendResponse([
            'success' => true,
            'data' => $topic
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found'
        ], 404);
    }
}


/**
 * Create a new topic.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   subject — string (required)
 *   message — string (required)
 *   author  — string (required)
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (missing fields): HTTP 400.
 *
 * Note: id and created_at are handled automatically by MySQL.
 */
function createTopic(PDO $db, array $data): void
{
    // Validate required fields.
    if (
        empty($data['subject']) ||
        empty($data['message']) ||
        empty($data['author'])
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Subject, message, and author are required'
        ], 400);
    }

    // Trim values.
    $subject = trim($data['subject']);
    $message = trim($data['message']);
    $author  = trim($data['author']);

    // Insert topic.
    $query = "INSERT INTO topics (subject, message, author)
              VALUES (?, ?, ?)";

    $stmt = $db->prepare($query);
    $stmt->execute([$subject, $message, $author]);

    // Check insertion result.
    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Topic created successfully',
            'id' => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create topic'
        ], 500);
    }
}


/**
 * Update an existing topic.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the topic to update (required).
 * Optional JSON body fields (at least one must be present):
 *   subject, message.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function updateTopic(PDO $db, array $data): void
{
// Validate id.
    if (empty($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Topic id is required'
        ], 400);
    }

    $id = $data['id'];

    // Check if topic exists.
    $checkQuery = "SELECT id FROM topics WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found'
        ], 404);
    }

    // Build dynamic SET clause.
    $fields = [];
    $values = [];

    if (isset($data['subject'])) {
        $fields[] = "subject = ?";
        $values[] = trim($data['subject']);
    }

    if (isset($data['message'])) {
        $fields[] = "message = ?";
        $values[] = trim($data['message']);
    }

    // Check if there are fields to update.
    if (empty($fields)) {
        sendResponse([
            'success' => false,
            'message' => 'No fields provided for update'
        ], 400);
    }

    // Build update query.
    $query = "UPDATE topics SET " . implode(', ', $fields) . " WHERE id = ?";

    // Add id to values array.
    $values[] = $id;

    // Execute update.
    $stmt = $db->prepare($query);

    if ($stmt->execute($values)) {
        sendResponse([
            'success' => true,
            'message' => 'Topic updated successfully'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to update topic'
        ], 500);
    }
}


/**
 * Delete a topic by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on replies.topic_id automatically
 * removes all replies for this topic — no manual deletion of replies
 * is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteTopic(PDO $db, $id): void
{
    // Validate id.
    if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid topic id'
        ], 400);
    }

    // Check if topic exists.
    $checkQuery = "SELECT id FROM topics WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found'
        ], 404);
    }

    // Delete topic.
    $query = "DELETE FROM topics WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);

    // Check deletion result.
    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Topic deleted successfully'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete topic'
        ], 500);
    }
}


// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

/**
 * Get all replies for a specific topic.
 * Method: GET with ?action=replies&topic_id={id}.
 *
 * Reads from the replies table.
 * Returns an empty data array if no replies exist — not an error.
 *
 * Each reply object: { id, topic_id, text, author, created_at }
 */
function getRepliesByTopicId(PDO $db, $topicId): void
{
   // Validate topic id.
    if (!$topicId || !is_numeric($topicId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid topic id'
        ], 400);
    }

    // Select replies.
    $query = "SELECT id, topic_id, text, author, created_at
              FROM replies
              WHERE topic_id = ?
              ORDER BY created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([$topicId]);

    // Fetch all replies.
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response.
    sendResponse([
        'success' => true,
        'data' => $replies
    ]);
}


/**
 * Create a new reply.
 * Method: POST with ?action=reply.
 *
 * Required JSON body:
 *   topic_id — integer FK into topics.id (required)
 *   text     — string (required, must be non-empty after trim)
 *   author   — string (required)
 *
 * Response (success): HTTP 201 — { success, message, id, data: reply }
 * Response (topic not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 *
 * Note: id and created_at are handled automatically by MySQL.
 */
function createReply(PDO $db, array $data): void
{
     // Validate required fields.
    if (
        empty($data['topic_id']) ||
        empty(trim($data['text'] ?? '')) ||
        empty(trim($data['author'] ?? ''))
    ) {
        sendResponse([
            'success' => false,
            'message' => 'topic_id, text, and author are required'
        ], 400);
    }

    // Validate topic_id.
    if (!is_numeric($data['topic_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid topic id'
        ], 400);
    }

    $topicId = $data['topic_id'];
    $text    = trim($data['text']);
    $author  = trim($data['author']);

    // Check if topic exists.
    $checkQuery = "SELECT id FROM topics WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$topicId]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found'
        ], 404);
    }

    // Insert reply.
    $query = "INSERT INTO replies (topic_id, text, author)
              VALUES (?, ?, ?)";

    $stmt = $db->prepare($query);
    $stmt->execute([$topicId, $text, $author]);

    // Check insertion result.
    if ($stmt->rowCount() > 0) {

        $newId = (int)$db->lastInsertId();

        $reply = [
            'id' => $newId,
            'topic_id' => (int)$topicId,
            'text' => $text,
            'author' => $author
        ];

        sendResponse([
            'success' => true,
            'message' => 'Reply created successfully',
            'id' => $newId,
            'data' => $reply
        ], 201);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to create reply'
        ], 500);
    }
}


/**
 * Delete a single reply.
 * Method: DELETE with ?action=delete_reply&id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteReply(PDO $db, $replyId): void
{
      // Validate reply id.
    if (!$replyId || !is_numeric($replyId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid reply id'
        ], 400);
    }

    // Check if reply exists.
    $checkQuery = "SELECT id FROM replies WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$replyId]);

    if (!$checkStmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Reply not found'
        ], 404);
    }

    // Delete reply.
    $query = "DELETE FROM replies WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$replyId]);

    // Check deletion result.
    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Reply deleted successfully'
        ], 200);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete reply'
        ], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);

        } elseif ($id !== null) {
            getTopicById($db, $id);

        } else {
            getAllTopics($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'reply') {
            createReply($db, $data);

        } else {
            createTopic($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateTopic($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_reply') {
            deleteReply($db, $id);

        } else {
            deleteTopic($db, $id);
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
        'message' => 'Database error occurred'
    ], 500);

} catch (Exception $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Server error occurred'
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
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
