<?php
function get_env_variable($name, $default = null) {
    static $envCache = null;
    if ($envCache === null) {
        $envCache = [];
        $paths = [dirname(__DIR__) . '/.env', __DIR__ . '/.env'];
        foreach ($paths as $envPath) {
            if (!@file_exists($envPath)) continue;
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) continue;
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
                [$key, $value] = explode('=', $line, 2);
                $envCache[trim($key)] = trim($value);
            }
            break;
        }
    }
    return $envCache[$name] ?? getenv($name) ?: $default;
}

$host   = get_env_variable('DB_HOST', 'localhost');
$user   = get_env_variable('DB_USER', 'root');
$pass   = get_env_variable('DB_PASSWORD', '');
$dbname = get_env_variable('DB_NAME', 'mtsk_dekont');
$port   = get_env_variable('DB_PORT', '3306');

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('[MTSK DB] ' . $e->getMessage());
    echo json_encode(['error' => 'Veritabani baglantisi kurulamiyor. Lutfen site yoneticisiyle iletisime gecin.']);
    exit();
}
?>
