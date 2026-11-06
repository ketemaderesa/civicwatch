<?php
/**
 * CivicWatch – PDO database connection
 */

declare(strict_types=1);

/**
 * Returns a shared PDO connection for the current request.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        http_response_code(500);
        $message = APP_DEBUG
            ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            : 'The database connection could not be established.';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Database unavailable · CivicWatch</title>'
            . '<style>body{font-family:system-ui,Segoe UI,sans-serif;background:#f2f5f9;color:#152238;'
            . 'display:grid;place-items:center;min-height:100vh;margin:0}.box{max-width:680px;background:#fff;'
            . 'padding:36px;border-radius:18px;box-shadow:0 20px 60px rgba(11,31,58,.12)}h1{margin-top:0;'
            . 'color:#0b1f3a}code{background:#eef2f7;padding:2px 6px;border-radius:5px}</style></head><body>'
            . '<div class="box"><h1>Database unavailable</h1>'
            . '<p>CivicWatch could not connect to MySQL. Start MySQL in the XAMPP Control Panel, '
            . 'then verify the credentials in <code>config/config.php</code>.</p>'
            . '<p><strong>Error:</strong> ' . $message . '</p></div></body></html>';
        exit;
    }

    return $pdo;
}
