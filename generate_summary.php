<?php
// Ensure no output is sent before headers
if (ob_get_level()) ob_end_clean();

// Set strict content type first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Disable error display in production
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Include required files
    require_once 'load_env.php';
    require_once 'connect.php';
    require_once 'ai_summary.php';
    
    // Start session securely
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_secure' => true,
            'cookie_httponly' => true
        ]);
    }

    if (!isset($_SESSION['email'])) {
        throw new Exception('Authentication required');
    }

    $summarizer = new TaskSummarizer($conn);
    $summary = $summarizer->generateDailySummary($_SESSION['email']);
    
    // Ensure no output before this
    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
    exit;

} catch (Exception $e) {
    // Log the error for debugging
    error_log('Summary generation error: ' . $e->getMessage());
    
    // Return clean JSON error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate summary'
    ]);
    exit;
}
?>