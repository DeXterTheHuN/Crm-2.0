<?php
/**
 * Központi Error Handler
 * Minden hibát egységesen kezel
 */

// Error reporting beállítása (production-ben kapcsold ki a display-t)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Egyedi exception handler
set_exception_handler(function($exception) {
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Szerverhiba történt',
            'code' => 'SERVER_ERROR'
        ]);
    } else {
        header('Location: error.php?code=500');
    }
    exit;
});

// Egyedi error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Shutdown handler (fatal errors)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kritikus hiba történt',
                'code' => 'FATAL_ERROR'
            ]);
        } else {
            header('Location: error.php?code=500');
        }
    }
});

// AJAX kérés ellenőrzése
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// JSON API válasz helper
function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sikeres válasz
function jsonSuccess($data = [], $message = 'Sikeres művelet') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

// Hiba válasz
function jsonError($message, $code = 'ERROR', $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], $statusCode);
}

// Redirect helper hibaüzenettel
function redirectWithError($url, $errorCode) {
    header("Location: {$url}?error={$errorCode}");
    exit;
}

// Redirect helper sikerüzenettel
function redirectWithSuccess($url, $message) {
    header("Location: {$url}?success=" . urlencode($message));
    exit;
}
