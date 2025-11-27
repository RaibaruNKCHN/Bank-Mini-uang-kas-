<?php
// Helper to show a friendly error page by redirecting to /error.php
// Usage: require_once __DIR__.'/error_handler.php'; show_error_page('DB error', __FILE__, __LINE__, $trace);

function show_error_page($message, $file = null, $line = null, $trace = null, $debug = false) {
    $payload = array(
        'message' => (string)$message,
        'file' => $file,
        'line' => $line,
        'trace' => $trace,
        'debug' => $debug
    );

    // Ensure logs directory exists (project-level /minibank/logs)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Create a short id for lookup
    $id = substr(sha1(uniqid((string)microtime(true), true)), 0, 10);
    $entry = array(
        'id' => $id,
        'ts' => date('c'),
        'message' => (string)$message,
        'file' => $file,
        'line' => $line,
        'trace' => $trace,
        'debug' => $debug,
        'server' => array('REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '', 'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '')
    );
    $logFile = $logDir . '/error.log';
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Include log id in payload so error.php can show it
    $payload['log_id'] = $id;
    $b = base64_encode(json_encode($payload));

    // Build absolute URL to error.php based on current SCRIPT_NAME
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $basePath = $scriptDir ?: '';
    // If script is inside a subfolder, error.php is expected at the project root next to minibank folder
    $candidate = $scheme . '://' . $host . $basePath . '/error.php?e=' . urlencode($b);
    // Fix duplicated slashes after host
    $candidate = preg_replace('#(?<!:)//+#', '/', $candidate);

    header('Location: ' . $candidate);
    exit;
}

// Optional convenience: register error/exception handlers that forward to error page
function register_error_handlers($allowDebug = false) {
    // Register exception handler which will forward to the friendly error page
    set_exception_handler(function($ex) use ($allowDebug) {
        $msg = $ex->getMessage();
        show_error_page($msg, $ex->getFile(), $ex->getLine(), $ex->getTraceAsString(), $allowDebug);
    });

    // Convert only serious PHP errors to exceptions so notices/warnings do not redirect users.
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($allowDebug) {
        $serious = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
        if (!($errno & $serious)) {
            // Let standard PHP handler deal with non-serious errors (notice, warning)
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
}
