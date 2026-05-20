<?php
/**
 * Course Resources API
 * 
 * This is a RESTful API that handles all CRUD operations for course resources 
 * and their associated comments/discussions.
 * It uses PDO to interact with a MySQL database.
 * 
 * Database Table Structures (for reference):
 * 
 * Table: resources
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(255), NOT NULL)
 *   - description (TEXT, nullable)
 *   - link (VARCHAR(500), NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * Table: comments_resource
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - resource_id (INT UNSIGNED, FOREIGN KEY references resources.id, CASCADE DELETE)
 *   - author (VARCHAR(100), NOT NULL)
 *   - text (TEXT, NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * HTTP Methods Supported:
 *   - GET:    Retrieve resource(s) or comment(s)
 *   - POST:   Create a new resource or comment
 *   - PUT:    Update an existing resource
 *   - DELETE: Delete a resource (associated comments in comments_resource are
 *             removed automatically by the ON DELETE CASCADE constraint)
 * 
 * Response Format: JSON
 * All responses follow the structure:
 *   { "success": true,  "data": ...    }  (on success)
 *   { "success": false, "message": ... }  (on error)
 * 
 * API Endpoints:
 * 
 *   Resources:
 *     GET    /resources/api/index.php                         - Get all resources
 *     GET    /resources/api/index.php?id={id}                 - Get single resource by ID
 *     POST   /resources/api/index.php                         - Create new resource
 *     PUT    /resources/api/index.php                         - Update resource
 *     DELETE /resources/api/index.php?id={id}                 - Delete resource
 * 
 *   Comments:
 *     GET    /resources/api/index.php?resource_id={id}&action=comments
 *                                                             - Get all comments for a resource
 *     POST   /resources/api/index.php?action=comment          - Create a new comment
 *     DELETE /resources/api/index.php?comment_id={id}&action=delete_comment
 *                                                             - Delete a single comment
 * 
 * Query Parameters for GET all resources:
 *   - search: Optional. Filter resources by title or description using LIKE.
 *   - sort:   Optional. Sort field — allowed values: title, created_at (default: created_at).
 *   - order:  Optional. Sort direction — allowed values: asc, desc (default: desc).
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type, Authorization");


// TODO: Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the database connection file
require_once './config/Database.php';


// TODO: Get the PDO database connection
$database = new Database();

$db = $database->getConnection();


// TODO: Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
$rawData = file_get_contents('php://input');

$data = json_decode($rawData, true);


// TODO: Parse query parameters from $_GET
$action = $_GET['action'] ?? null;

$id = $_GET['id'] ?? null;

$resource_id = $_GET['resource_id'] ?? null;

$comment_id = $_GET['comment_id'] ?? null;

// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Function: Get all resources
 * Method: GET (no id or action parameter)
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by title or description
 *   - sort:   Optional field to sort by — allowed values: title, created_at
 *   - order:  Optional sort direction — allowed values: asc, desc (default: desc)
 * 
 * Response:
 *   { "success": true, "data": [ ...resource objects ] }
 */
function getAllResources($db) {
  $query = "SELECT id, title, description, link, created_at FROM resources";

    $search = $_GET['search'] ?? '';

    if (!empty($search)) {

        $query .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $allowedSort = ['title', 'created_at'];

    $sort = $_GET['sort'] ?? 'created_at';

    if (!in_array($sort, $allowedSort)) {

        $sort = 'created_at';
    }

    $allowedOrder = ['asc', 'desc'];

    $order = strtolower($_GET['order'] ?? 'desc');

    if (!in_array($order, $allowedOrder)) {

        $order = 'desc';
    }

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    if (!empty($search)) {

        $stmt->bindValue(':search', '%' . $search . '%');
    }

    $stmt->execute();

    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $resources
    ]);
}


/**
 * Function: Get a single resource by ID
 * Method: GET with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   { "success": true, "data": { id, title, description, link, created_at } }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 */
function getResourceById($db, $resourceId) {
 if (!$resourceId || !is_numeric($resourceId)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $query = "SELECT id, title, description, link, created_at 
              FROM resources 
              WHERE id = ?";

    $stmt = $db->prepare($query);

    $stmt->execute([$resourceId]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {

        sendResponse([
            'success' => true,
            'data' => $resource
        ]);
    } else {

        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }
}


/**
 * Function: Create a new resource
 * Method: POST (no action parameter)
 * 
 * Required JSON Body:
 *   - title:       Resource title (required)
 *   - description: Resource description (optional, defaults to empty string)
 *   - link:        URL to the resource (required, must be a valid URL)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new resource id> }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createResource($db, $data) {

    if (
        empty($data['title']) ||
        empty($data['link'])
    ) {

        sendResponse([
            'success' => false,
            'message' => 'Title and link are required.'
        ], 400);
    }

    $title = trim($data['title']);

    $link = trim($data['link']);

    $description = trim($data['description'] ?? '');

    if (!filter_var($link, FILTER_VALIDATE_URL)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid URL.'
        ], 400);
    }

    $query = "INSERT INTO resources 
              (title, description, link) 
              VALUES (?, ?, ?)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        $title,
        $description,
        $link
    ]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id' => $db->lastInsertId()
        ], 201);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to create resource.'
        ], 500);
    }
}


/**
 * Function: Update an existing resource
 * Method: PUT
 * 
 * Required JSON Body:
 *   - id:          The resource's database ID (required)
 *   - title:       Updated title (optional)
 *   - description: Updated description (optional)
 *   - link:        Updated URL (optional, must be a valid URL if provided)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource updated successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function updateResource($db, $data) {

    if (empty($data['id'])) {

        sendResponse([
            'success' => false,
            'message' => 'Resource ID is required.'
        ], 400);
    }

    $id = $data['id'];

    $checkQuery = "SELECT id FROM resources WHERE id = ?";

    $checkStmt = $db->prepare($checkQuery);

    $checkStmt->execute([$id]);

    $resource = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {

        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $fields = [];

    $values = [];

    if (isset($data['title'])) {

        $fields[] = "title = ?";

        $values[] = trim($data['title']);
    }

    if (isset($data['description'])) {

        $fields[] = "description = ?";

        $values[] = trim($data['description']);
    }

    if (isset($data['link'])) {

        $link = trim($data['link']);

        if (!filter_var($link, FILTER_VALIDATE_URL)) {

            sendResponse([
                'success' => false,
                'message' => 'Invalid URL.'
            ], 400);
        }

        $fields[] = "link = ?";

        $values[] = $link;
    }

    if (empty($fields)) {

        sendResponse([
            'success' => false,
            'message' => 'No fields provided for update.'
        ], 400);
    }

    $query = "UPDATE resources 
              SET " . implode(', ', $fields) . "
              WHERE id = ?";

    $values[] = $id;

    $stmt = $db->prepare($query);

    if ($stmt->execute($values)) {

        sendResponse([
            'success' => true,
            'message' => 'Resource updated successfully.'
        ], 200);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to update resource.'
        ], 500);
    }
}


/**
 * Function: Delete a resource
 * Method: DELETE with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * 
 * Note: All associated comments in comments_resource are deleted automatically
 *       by the ON DELETE CASCADE foreign key constraint — no manual deletion
 *       of comments is needed.
 */
function deleteResource($db, $resourceId) {
 if (!$resourceId || !is_numeric($resourceId)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $checkQuery = "SELECT id FROM resources WHERE id = ?";

    $checkStmt = $db->prepare($checkQuery);

    $checkStmt->execute([$resourceId]);

    $resource = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {

        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $query = "DELETE FROM resources WHERE id = ?";

    $stmt = $db->prepare($query);

    $stmt->execute([$resourceId]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ], 200);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to delete resource.'
        ], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific resource
 * Method: GET with ?resource_id={id}&action=comments
 * 
 * Query Parameters:
 *   - resource_id: The resource's database ID (required)
 * 
 * Response:
 *   { "success": true, "data": [ ...comment objects ] }
 *   Returns an empty data array if no comments exist (not an error).
 *
 * Each comment object: { id, resource_id, author, text, created_at }
 */
function getCommentsByResourceId($db, $resourceId) {
  if (!$resourceId || !is_numeric($resourceId)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $query = "SELECT id, resource_id, author, text, created_at
              FROM comments_resource
              WHERE resource_id = ?
              ORDER BY created_at ASC";

    $stmt = $db->prepare($query);

    $stmt->execute([$resourceId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}


/**
 * Function: Create a new comment
 * Method: POST with ?action=comment
 * 
 * Required JSON Body:
 *   - resource_id: The resource's database ID (required, must be numeric)
 *   - author:      Name of the comment author (required)
 *   - text:        Comment text content (required)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new comment id> }
 * Response (resource not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createComment($db, $data) {

    if (
        empty($data['resource_id']) ||
        empty($data['author']) ||
        empty($data['text'])
    ) {

        sendResponse([
            'success' => false,
            'message' => 'Resource ID, author, and text are required.'
        ], 400);
    }

    $resource_id = $data['resource_id'];

    if (!is_numeric($resource_id)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid resource ID.'
        ], 400);
    }

    $checkQuery = "SELECT id FROM resources WHERE id = ?";

    $checkStmt = $db->prepare($checkQuery);

    $checkStmt->execute([$resource_id]);

    $resource = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {

        sendResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
    }

    $author = trim($data['author']);

    $text = trim($data['text']);

    $query = "INSERT INTO comments_resource 
              (resource_id, author, text) 
              VALUES (?, ?, ?)";

    $stmt = $db->prepare($query);

    $stmt->execute([
        $resource_id,
        $author,
        $text
    ]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $db->lastInsertId()
        ], 201);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to create comment.'
        ], 500);
    }
}


/**
 * Function: Delete a comment
 * Method: DELETE with ?comment_id={id}&action=delete_comment
 * 
 * Query Parameters:
 *   - comment_id: The comment's database ID (required)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Comment deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Comment not found." }
 */
function deleteComment($db, $commentId) {
  if (!$commentId || !is_numeric($commentId)) {

        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID.'
        ], 400);
    }

    $checkQuery = "SELECT id FROM comments_resource WHERE id = ?";

    $checkStmt = $db->prepare($checkQuery);

    $checkStmt->execute([$commentId]);

    $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {

        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $query = "DELETE FROM comments_resource WHERE id = ?";

    $stmt = $db->prepare($query);

    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ], 200);

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment.'
        ], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
  
    if ($method === 'GET') {

        if ($action === 'comments') {

            getCommentsByResourceId($db, $resource_id);

        } elseif ($id) {

            getResourceById($db, $id);

        } else {

            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {

            createComment($db, $data);

        } else {

            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {

            deleteComment($db, $comment_id);

        } else {

            deleteResource($db, $id);
        }

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Database error occurred.'
    ], 500);

} catch (Exception $e) {

    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Server error occurred.'
    ], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper: Send a JSON response and stop execution.
 * 
 * @param array $data        Response payload. Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default: 200).
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Set the HTTP status code using http_response_code()

    // TODO: Ensure $data is an array; if not, wrap it

    // TODO: Echo json_encode($data) and call exit
}


/**
 * Helper: Validate a URL string.
 * 
 * @param  string $url
 * @return bool  True if the URL passes FILTER_VALIDATE_URL, false otherwise.
 */
function validateUrl($url) {
   http_response_code($statusCode);

    if (!is_array($data)) {

        $data = ['message' => $data];
    }

    echo json_encode($data);

    exit;
}


/**
 * Helper: Sanitize a single input string.
 * 
 * @param  string $data
 * @return string  Trimmed, tag-stripped, and HTML-encoded string.
 */
function sanitizeInput($data) {
       return htmlspecialchars(
        strip_tags(
            trim($data)
        ),
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Helper: Check that all required fields exist and are non-empty in $data.
 * 
 * @param  array $data            Associative array of input data.
 * @param  array $requiredFields  List of field names that must be present.
 * @return array  ['valid' => bool, 'missing' => string[]]
 */
function validateRequiredFields($data, $requiredFields) {
  $missing = [];

    foreach ($requiredFields as $field) {

        if (
            !isset($data[$field]) ||
            empty(trim($data[$field]))
        ) {

            $missing[] = $field;
        }
    }

    return [
        'valid' => (count($missing) === 0),
        'missing' => $missing
    ];
}

?>
