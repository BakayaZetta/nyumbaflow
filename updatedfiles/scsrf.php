<?php
/**
 * CSRF Enforcement Layer
 */
require_once __DIR__ . '/csrf_token.php';

// Initialize the token
generate_csrf_token();

// Automated check for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Exempt API routes since they do not use session cookies
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';
    
    // Check if it's an AJAX/JSON request with the token in headers
    if (empty($token)) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-TOKEN'] ?? $headers['x-csrf-token'] ?? '';
    }

    if (!verify_csrf_token($token)) {
        // If it's a JSON request, return JSON
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed.']);
            exit;
        }

        // Standard response
        http_response_code(403);
        die("CSRF token validation failed. Please refresh the page and try again.");
    }
}
?>
