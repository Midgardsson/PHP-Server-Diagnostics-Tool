<?php
/**
 * PHP Server Diagnostics Tool
 * Visual diagnostic tool for PHP environment analysis
 *
 * WARNING: Delete this file after use! Contains sensitive system information.
 */

// Configuration constants
define('REDIS_TIMEOUT', 1);
define('MEMCACHED_TIMEOUT', 1);
define('MYSQL_TIMEOUT', 1);
define('POSTGRES_TIMEOUT', 2);
define('MONGODB_TIMEOUT', 1);

define('REDIS_DEFAULT_PORT', 6379);
define('MEMCACHED_DEFAULT_PORT', 11211);
define('MYSQL_DEFAULT_PORT', 3306);
define('POSTGRES_DEFAULT_PORT', 5432);
define('MONGODB_DEFAULT_PORT', 27017);

/**
 * Format bytes to readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Status badge color
 */
function getStatusColor($value, $type = 'boolean') {
    if ($type === 'boolean') {
        return $value ? '#10b981' : '#ef4444';
    }
    return '#6366f1';
}

/**
 * Try multiple connection attempts with a callback
 *
 * @param array $attempts Array of connection configurations
 * @param callable $connectCallback Function to attempt connection
 * @return array|null Connection result or null
 */
function tryConnections(array $attempts, callable $connectCallback) {
    foreach ($attempts as $attempt) {
        try {
            $result = $connectCallback($attempt);
            if ($result !== null && $result !== false) {
                return $result;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    return null;
}

/**
 * Get PHP Extensions grouped by category
 */
function getExtensionsByCategory() {
    $loaded = get_loaded_extensions();
    sort($loaded);

    $categories = [
        'Database' => ['mysqli', 'pdo', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'pgsql', 'sqlite3', 'mongodb', 'redis'],
        'Cache' => ['apcu', 'opcache', 'memcached', 'redis', 'wincache'],
        'Compression' => ['zlib', 'bz2', 'zip', 'rar'],
        'Encryption' => ['openssl', 'mcrypt', 'sodium', 'hash'],
        'Image' => ['gd', 'imagick', 'exif'],
        'XML/JSON' => ['xml', 'xmlreader', 'xmlwriter', 'simplexml', 'dom', 'json', 'libxml'],
        'String/Text' => ['mbstring', 'iconv', 'intl', 'gettext'],
        'Network' => ['curl', 'ftp', 'sockets', 'ssh2'],
        'Other' => []
    ];

    $categorized = [];
    foreach ($categories as $cat => $exts) {
        $categorized[$cat] = [];
    }

    foreach ($loaded as $ext) {
        $placed = false;
        foreach ($categories as $cat => $exts) {
            if ($cat !== 'Other' && in_array(strtolower($ext), $exts)) {
                $categorized[$cat][] = $ext;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $categorized['Other'][] = $ext;
        }
    }

    return array_filter($categorized);
}

/**
 * OPcache information
 */
function getOPcacheInfo() {
    if (!function_exists('opcache_get_status')) {
        return null;
    }

    try {
        $status = opcache_get_status(false);
        $config = opcache_get_configuration();

        return [
            'enabled' => $status !== false,
            'status' => $status,
            'config' => $config
        ];
    } catch (Exception $e) {
        return [
            'enabled' => false,
            'status' => false,
            'config' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * APCu information
 */
function getAPCuInfo() {
    if (!function_exists('apcu_cache_info')) {
        return null;
    }

    try {
        $info = apcu_cache_info(true);
        $sma = apcu_sma_info(true);

        if ($info === false || $sma === false) {
            return null;
        }

        return [
            'enabled' => true,
            'info' => $info,
            'sma' => $sma
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Redis information
 */
function getRedisInfo() {
    if (!extension_loaded('redis')) {
        return null;
    }

    $attempts = [
        ['type' => 'unix_socket', 'path' => '/var/run/redis/redis-server.sock'],
        ['type' => 'unix_socket', 'path' => '/var/run/redis/redis.sock'],
        ['type' => 'unix_socket', 'path' => '/tmp/redis.sock'],
        ['type' => 'tcp', 'host' => '127.0.0.1', 'port' => REDIS_DEFAULT_PORT],
        ['type' => 'tcp', 'host' => 'localhost', 'port' => REDIS_DEFAULT_PORT],
    ];

    $result = tryConnections($attempts, function($attempt) {
        $redis = new Redis();

        if ($attempt['type'] === 'unix_socket') {
            if (!file_exists($attempt['path'])) {
                return null;
            }
            $connected = $redis->connect($attempt['path']);
            $connectionType = 'Unix Socket: ' . $attempt['path'];
        } else {
            $connected = $redis->connect($attempt['host'], $attempt['port'], REDIS_TIMEOUT);
            $connectionType = 'TCP: ' . $attempt['host'] . ':' . $attempt['port'];
        }

        if (!$connected) {
            return null;
        }

        try {
            $info = $redis->info();
            $dbsize = $redis->dbSize();

            // Memory information
            $usedMemory = $info['used_memory'] ?? 0;
            $maxMemory = $info['maxmemory'] ?? 0;

            // Stats
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

            return [
                'enabled' => true,
                'connected' => true,
                'connection_type' => $connectionType,
                'version' => $info['redis_version'] ?? 'N/A',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'used_memory' => $usedMemory,
                'max_memory' => $maxMemory,
                'total_keys' => $dbsize,
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => $hitRate,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'evicted_keys' => $info['evicted_keys'] ?? 0,
                'expired_keys' => $info['expired_keys'] ?? 0,
                'redis_mode' => $info['redis_mode'] ?? 'standalone',
                'os' => $info['os'] ?? 'N/A',
                'process_id' => $info['process_id'] ?? 'N/A'
            ];
        } catch (Exception $e) {
            return [
                'enabled' => true,
                'connected' => true,
                'error' => $e->getMessage()
            ];
        }
    });

    if ($result === null) {
        return [
            'enabled' => true,
            'connected' => false,
            'error' => 'Could not connect to Redis server'
        ];
    }

    return $result;
}

/**
 * Memcached information
 */
function getMemcachedInfo() {
    if (!extension_loaded('memcached') && !extension_loaded('memcache')) {
        return null;
    }

    $extension = extension_loaded('memcached') ? 'memcached' : 'memcache';

    if ($extension !== 'memcached') {
        return [
            'enabled' => true,
            'connected' => false,
            'extension' => $extension,
            'error' => 'Only memcached extension is supported'
        ];
    }

    try {
        $memcached = new Memcached();
        $servers = [
            ['127.0.0.1', MEMCACHED_DEFAULT_PORT],
            ['localhost', MEMCACHED_DEFAULT_PORT],
            ['/var/run/memcached/memcached.sock', 0]
        ];

        foreach ($servers as $server) {
            $memcached->addServer($server[0], $server[1]);
        }

        $stats = $memcached->getStats();
        if ($stats && !empty($stats)) {
            $serverKey = array_key_first($stats);
            $stat = $stats[$serverKey];

            if ($stat && isset($stat['pid'])) {
                $total = ($stat['get_hits'] ?? 0) + ($stat['get_misses'] ?? 0);

                return [
                    'enabled' => true,
                    'connected' => true,
                    'extension' => $extension,
                    'connection' => $serverKey,
                    'version' => $stat['version'] ?? 'N/A',
                    'uptime' => $stat['uptime'] ?? 0,
                    'curr_items' => $stat['curr_items'] ?? 0,
                    'total_items' => $stat['total_items'] ?? 0,
                    'bytes' => $stat['bytes'] ?? 0,
                    'limit_maxbytes' => $stat['limit_maxbytes'] ?? 0,
                    'get_hits' => $stat['get_hits'] ?? 0,
                    'get_misses' => $stat['get_misses'] ?? 0,
                    'evictions' => $stat['evictions'] ?? 0,
                    'curr_connections' => $stat['curr_connections'] ?? 0,
                    'hit_rate' => $total > 0 ? (($stat['get_hits'] ?? 0) / $total) * 100 : 0
                ];
            }
        }

        return [
            'enabled' => true,
            'connected' => false,
            'extension' => $extension,
            'error' => 'Could not retrieve stats'
        ];
    } catch (Exception $e) {
        return [
            'enabled' => true,
            'connected' => false,
            'extension' => $extension,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * MySQL/MariaDB information
 */
function getMySQLInfo() {
    if (!extension_loaded('mysqli')) {
        return null;
    }

    $attempts = [
        ['host' => 'localhost', 'socket' => '/var/run/mysqld/mysqld.sock'],
        ['host' => '127.0.0.1', 'socket' => null],
        ['host' => 'localhost', 'socket' => '/tmp/mysql.sock'],
    ];

    $result = tryConnections($attempts, function($attempt) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = new mysqli($attempt['host'], '', '', '', 0, $attempt['socket']);

        if ($mysqli->connect_error) {
            return null;
        }

        $data = [
            'enabled' => true,
            'connected' => true,
            'connection' => $attempt['socket'] ?: $attempt['host']
        ];

        // Version
        $version = $mysqli->get_server_info();
        $data['version'] = $version;
        $data['is_mariadb'] = stripos($version, 'mariadb') !== false;

        // Status variables
        $status = [];
        $res = $mysqli->query("SHOW GLOBAL STATUS");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status[$row['Variable_name']] = $row['Value'];
            }
            $res->close();
        }

        $data['uptime'] = $status['Uptime'] ?? 0;
        $data['threads_connected'] = $status['Threads_connected'] ?? 0;
        $data['questions'] = $status['Questions'] ?? 0;
        $data['queries'] = $status['Queries'] ?? 0;
        $data['bytes_received'] = $status['Bytes_received'] ?? 0;
        $data['bytes_sent'] = $status['Bytes_sent'] ?? 0;

        // InnoDB buffer pool if available
        if (isset($status['Innodb_buffer_pool_pages_total'])) {
            $data['innodb_buffer_pool_size'] = ($status['Innodb_buffer_pool_pages_total'] ?? 0) * 16384; // page size 16KB
            $data['innodb_buffer_pool_pages_free'] = $status['Innodb_buffer_pool_pages_free'] ?? 0;
        }

        $mysqli->close();
        return $data;
    });

    if ($result === null) {
        return [
            'enabled' => true,
            'connected' => false
        ];
    }

    return $result;
}

/**
 * PostgreSQL information
 */
function getPostgreSQLInfo() {
    if (!extension_loaded('pgsql')) {
        return null;
    }

    $attempts = [
        "host=localhost port=" . POSTGRES_DEFAULT_PORT . " dbname=postgres user=postgres connect_timeout=" . POSTGRES_TIMEOUT,
        "host=127.0.0.1 port=" . POSTGRES_DEFAULT_PORT . " dbname=postgres connect_timeout=" . POSTGRES_TIMEOUT,
        "host=/var/run/postgresql dbname=postgres connect_timeout=" . POSTGRES_TIMEOUT
    ];

    $result = tryConnections($attempts, function($connstr) {
        $conn = pg_connect($connstr);
        if (!$conn) {
            return null;
        }

        $data = [
            'enabled' => true,
            'connected' => true,
            'connection' => $connstr
        ];

        // Version
        $version = pg_version($conn);
        $data['server_version'] = $version['server'] ?? 'N/A';
        $data['client_version'] = $version['client'] ?? 'N/A';

        // Database count
        $res = pg_query($conn, "SELECT count(*) as db_count FROM pg_database WHERE datistemplate = false");
        if ($res) {
            $row = pg_fetch_assoc($res);
            $data['database_count'] = $row['db_count'] ?? 0;
        }

        // Connections
        $res = pg_query($conn, "SELECT count(*) as conn_count FROM pg_stat_activity");
        if ($res) {
            $row = pg_fetch_assoc($res);
            $data['active_connections'] = $row['conn_count'] ?? 0;
        }

        pg_close($conn);
        return $data;
    });

    if ($result === null) {
        return [
            'enabled' => true,
            'connected' => false
        ];
    }

    return $result;
}

/**
 * PDO connection information
 */
function getPDOInfo() {
    if (!extension_loaded('pdo')) {
        return null;
    }

    $pdoDrivers = [];
    $availableDrivers = PDO::getAvailableDrivers();

    foreach ($availableDrivers as $driver) {
        $driverInfo = [
            'driver' => $driver,
            'available' => true,
            'connected' => false
        ];

        try {
            // MySQL PDO
            if ($driver === 'mysql') {
                $attempts = [
                    ['dsn' => 'mysql:host=localhost;charset=utf8mb4', 'socket' => '/var/run/mysqld/mysqld.sock'],
                    ['dsn' => 'mysql:host=127.0.0.1;charset=utf8mb4', 'socket' => null],
                    ['dsn' => 'mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4', 'socket' => '/tmp/mysql.sock'],
                ];

                $result = tryConnections($attempts, function($attempt) {
                    $pdo = new PDO($attempt['dsn'], '', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => MYSQL_TIMEOUT
                    ]);
                    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                    return [
                        'connected' => true,
                        'connection' => $attempt['socket'] ?: 'TCP',
                        'version' => $version,
                        'is_mariadb' => stripos($version, 'mariadb') !== false
                    ];
                });

                if ($result) {
                    $driverInfo = array_merge($driverInfo, $result);
                }
            }

            // PostgreSQL PDO
            if ($driver === 'pgsql') {
                $attempts = [
                    ['dsn' => 'pgsql:host=localhost;port=' . POSTGRES_DEFAULT_PORT . ';dbname=postgres'],
                    ['dsn' => 'pgsql:host=127.0.0.1;port=' . POSTGRES_DEFAULT_PORT . ';dbname=postgres'],
                    ['dsn' => 'pgsql:host=/var/run/postgresql;dbname=postgres']
                ];

                $result = tryConnections($attempts, function($attempt) {
                    $pdo = new PDO($attempt['dsn'], 'postgres', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => POSTGRES_TIMEOUT
                    ]);
                    $version = $pdo->query('SELECT version()')->fetchColumn();
                    $versionParts = explode(' on ', $version);
                    return [
                        'connected' => true,
                        'connection' => $attempt['dsn'],
                        'version' => $versionParts[0] ?? $version
                    ];
                });

                if ($result) {
                    $driverInfo = array_merge($driverInfo, $result);
                }
            }

            // SQLite PDO
            if ($driver === 'sqlite') {
                $pdo = new PDO('sqlite::memory:');
                $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
                $driverInfo['connected'] = true;
                $driverInfo['connection'] = 'In-Memory';
                $driverInfo['version'] = $version;
            }
        } catch (Exception $e) {
            $driverInfo['error'] = $e->getMessage();
        }

        $pdoDrivers[] = $driverInfo;
    }

    return !empty($pdoDrivers) ? $pdoDrivers : null;
}

/**
 * SQLite3 information
 */
function getSQLite3Info() {
    if (!extension_loaded('sqlite3')) {
        return null;
    }
    
    try {
        $version = SQLite3::version();
        return [
            'enabled' => true,
            'version' => $version['versionString'],
            'version_number' => $version['versionNumber']
        ];
    } catch (Exception $e) {
        return [
            'enabled' => true,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * MongoDB information
 */
function getMongoDBInfo() {
    if (!extension_loaded('mongodb')) {
        return null;
    }

    try {
        $connectionString = "mongodb://localhost:" . MONGODB_DEFAULT_PORT . "/?connectTimeoutMS=" . (MONGODB_TIMEOUT * 1000);
        $manager = new MongoDB\Driver\Manager($connectionString);

        $command = new MongoDB\Driver\Command(['ping' => 1]);
        $manager->executeCommand('admin', $command);

        // Server status
        $command = new MongoDB\Driver\Command(['serverStatus' => 1]);
        $cursor = $manager->executeCommand('admin', $command);
        $status = current($cursor->toArray());

        $result = [
            'enabled' => true,
            'connected' => true,
            'connection' => 'localhost:' . MONGODB_DEFAULT_PORT
        ];

        if ($status) {
            $result['version'] = $status->version ?? 'N/A';
            $result['uptime'] = $status->uptime ?? 0;
            $result['connections'] = $status->connections->current ?? 0;
            $result['opcounters'] = [
                'insert' => $status->opcounters->insert ?? 0,
                'query' => $status->opcounters->query ?? 0,
                'update' => $status->opcounters->update ?? 0,
                'delete' => $status->opcounters->delete ?? 0,
            ];
        }

        return $result;
    } catch (Exception $e) {
        return [
            'enabled' => true,
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Disk usage information
 */
function getDiskInfo() {
    $disks = [];

    // Linux system df command
    if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
        $output = shell_exec('df -h 2>/dev/null');
        if ($output) {
            $lines = explode("\n", trim($output));
            array_shift($lines); // Header row

            $diskFilesystems = [];
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $filesystem = $parts[0];
                    // Skip temp filesystems and loops
                    if (strpos($filesystem, 'tmpfs') !== false ||
                        strpos($filesystem, 'loop') !== false ||
                        strpos($filesystem, 'devtmpfs') !== false) {
                        continue;
                    }

                    $diskFilesystems[] = $filesystem;
                    $disks[] = [
                        'filesystem' => $filesystem,
                        'size' => $parts[1],
                        'used' => $parts[2],
                        'available' => $parts[3],
                        'use_percent' => rtrim($parts[4], '%'),
                        'mounted' => $parts[5]
                    ];
                }
            }
        }

        // Inode information - match by filesystem name
        $inodeOutput = shell_exec('df -i 2>/dev/null');
        if ($inodeOutput && !empty($diskFilesystems)) {
            $lines = explode("\n", trim($inodeOutput));
            array_shift($lines);

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $filesystem = $parts[0];
                    // Find matching disk by filesystem
                    foreach ($disks as $index => $disk) {
                        if ($disk['filesystem'] === $filesystem) {
                            $disks[$index]['inode_use_percent'] = rtrim($parts[4], '%');
                            break;
                        }
                    }
                }
            }
        }
    } else {
        // Windows case
        $totalSpace = disk_total_space('C:');
        $freeSpace = disk_free_space('C:');
        if ($totalSpace && $freeSpace) {
            $disks[] = [
                'filesystem' => 'C:',
                'total' => $totalSpace,
                'free' => $freeSpace,
                'used' => $totalSpace - $freeSpace,
                'use_percent' => round((1 - $freeSpace / $totalSpace) * 100, 2)
            ];
        }
    }

    return $disks;
}

/**
 * System Load and CPU information
 */
function getSystemLoad() {
    $info = [
        'cpu_count' => 1
    ];

    // CPU count
    if (function_exists('shell_exec')) {
        if (PHP_OS_FAMILY === 'Linux') {
            $cpuinfo = shell_exec('nproc 2>/dev/null');
            if ($cpuinfo && trim($cpuinfo) !== '') {
                $info['cpu_count'] = max(1, (int)trim($cpuinfo));
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $cpuinfo = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($cpuinfo && trim($cpuinfo) !== '') {
                $info['cpu_count'] = max(1, (int)trim($cpuinfo));
            }
        }
    }

    // Load average (Linux/Unix only)
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if ($load && is_array($load) && count($load) >= 3) {
            $info['load_1min'] = $load[0];
            $info['load_5min'] = $load[1];
            $info['load_15min'] = $load[2];
            $info['load_percent_1min'] = ($load[0] / $info['cpu_count']) * 100;
        }
    }

    // Uptime
    if (PHP_OS_FAMILY === 'Linux') {
        $uptime = shell_exec('cat /proc/uptime 2>/dev/null');
        if ($uptime) {
            $parts = explode(' ', trim($uptime));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                $info['uptime_seconds'] = (int)$parts[0];
            }
        }
    }

    return $info;
}

/**
 * Memory information
 */
function getMemoryInfo() {
    return [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}

/**
 * PHP configuration files
 */
function getConfigFiles() {
    $files = [];

    if ($loaded = php_ini_loaded_file()) {
        $files['main'] = $loaded;
    }

    if ($scanned = php_ini_scanned_files()) {
        $files['additional'] = explode(',', $scanned);
    }

    return $files;
}

/**
 * Important PHP directives
 */
function getImportantDirectives() {
    return [
        'Performance' => [
            'max_execution_time',
            'max_input_time',
            'memory_limit',
            'post_max_size',
            'upload_max_filesize',
            'max_file_uploads'
        ],
        'Error Handling' => [
            'display_errors',
            'display_startup_errors',
            'error_reporting',
            'log_errors',
            'error_log'
        ],
        'Security' => [
            'expose_php',
            'allow_url_fopen',
            'allow_url_include',
            'disable_functions',
            'open_basedir'
        ],
        'Session' => [
            'session.save_handler',
            'session.save_path',
            'session.gc_maxlifetime',
            'session.cookie_lifetime',
            'session.cookie_httponly',
            'session.cookie_secure'
        ]
    ];
}

// Data collection
$phpVersion = phpversion();
$extensions = getExtensionsByCategory();
$opcache = getOPcacheInfo();
$apcu = getAPCuInfo();
$redis = getRedisInfo();
$memcached = getMemcachedInfo();
$mysql = getMySQLInfo();
$postgresql = getPostgreSQLInfo();
$mongodb = getMongoDBInfo();
$pdo = getPDOInfo();
$sqlite3 = getSQLite3Info();
$disks = getDiskInfo();
$systemLoad = getSystemLoad();
$memory = getMemoryInfo();
$configFiles = getConfigFiles();
$directives = getImportantDirectives();
$serverInfo = [
    'OS' => PHP_OS,
    'Server API' => php_sapi_name(),
    'Architecture' => PHP_INT_SIZE * 8 . '-bit',
    'Zend Version' => zend_version(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'Server Time' => date('Y-m-d H:i:s'),
    'Timezone' => date_default_timezone_get()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Diagnostics - Server Information</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f172a;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            border: 1px solid #334155;
            text-align: center;
        }

        .header h1 {
            color: #f1f5f9;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .php-version {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: #1e293b;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid #334155;
        }

        .card.full-width {
            grid-column: 1 / -1;
        }

        .card-header {
            background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #475569;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #f1f5f9;
        }

        .stat-row {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #94a3b8;
            font-weight: 500;
        }

        .stat-value {
            color: #e2e8f0;
            font-weight: 600;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.success {
            background: #10b981;
            color: white;
        }

        .badge.error {
            background: #ef4444;
            color: white;
        }

        .badge.warning {
            background: #f59e0b;
            color: white;
        }

        .metric-box {
            padding: 15px 20px;
            border-bottom: 1px solid #334155;
        }

        .metric-box:last-child {
            border-bottom: none;
        }

        .metric-title {
            color: #94a3b8;
            font-size: 0.9em;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .metric-value {
            color: #f1f5f9;
            font-size: 1.5em;
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #334155;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
            transition: width 0.3s ease;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }

        .category-section {
            padding: 20px;
            border-bottom: 1px solid #334155;
        }

        .category-section:last-child {
            border-bottom: none;
        }

        .category-title {
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .extension-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .extension-item {
            background: #334155;
            padding: 8px 12px;
            border-radius: 5px;
            color: #e2e8f0;
            font-size: 0.9em;
            border: 1px solid #475569;
        }

        .code-block {
            background: #0f172a;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #10b981;
            overflow-x: auto;
            border: 1px solid #334155;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .php-version {
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔍 PHP Server Diagnostics</h1>
            <div class="php-version">PHP <?= $phpVersion ?></div>
        </div>

        <!-- Server Info Grid -->
        <div class="grid">
            <!-- Memory Usage -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);">
                        💾
                    </div>
                    <div class="card-title">Memory Usage</div>
                </div>
                <div class="metric-box">
                    <div class="metric-title">Current Usage</div>
                    <div class="metric-value"><?= formatBytes($memory['current']) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-title">Peak Usage</div>
                    <div class="metric-value"><?= formatBytes($memory['peak']) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-title">Memory Limit</div>
                    <div class="metric-value"><?= $memory['limit'] ?></div>
                </div>
            </div>

            <!-- Server Information -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                        🖥️
                    </div>
                    <div class="card-title">Server Information</div>
                </div>
                <?php foreach ($serverInfo as $key => $value): ?>
                <div class="stat-row">
                    <span class="stat-label"><?= $key ?>:</span>
                    <span class="stat-value"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- System Load -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                        📊
                    </div>
                    <div class="card-title">System Load</div>
                </div>
                <div class="stat-row">
                    <span class="stat-label">CPU Cores:</span>
                    <span class="stat-value"><?= $systemLoad['cpu_count'] ?></span>
                </div>
                <?php if (isset($systemLoad['load_1min'])): ?>
                <div class="stat-row">
                    <span class="stat-label">Load Average (1min):</span>
                    <span class="stat-value"><?= number_format($systemLoad['load_1min'], 2) ?> (<?= number_format($systemLoad['load_percent_1min'], 1) ?>%)</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Load Average (5min):</span>
                    <span class="stat-value"><?= number_format($systemLoad['load_5min'], 2) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Load Average (15min):</span>
                    <span class="stat-value"><?= number_format($systemLoad['load_15min'], 2) ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $systemLoad['load_percent_1min'] > 90 ? 'danger' : ($systemLoad['load_percent_1min'] > 75 ? 'warning' : '') ?>"
                         style="width: <?= min($systemLoad['load_percent_1min'], 100) ?>%">
                        <?= number_format($systemLoad['load_percent_1min'], 1) ?>%
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($systemLoad['uptime_seconds'])): ?>
                <div class="stat-row">
                    <span class="stat-label">System Uptime:</span>
                    <span class="stat-value"><?= gmdate('H:i:s', $systemLoad['uptime_seconds']) ?> (<?= number_format($systemLoad['uptime_seconds'] / 86400, 1) ?> days)</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Disk Usage -->
            <?php if (!empty($disks)): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);">
                        💾
                    </div>
                    <div class="card-title">Disk Usage</div>
                </div>
                <?php foreach ($disks as $disk): ?>
                <div class="metric-box" style="margin-bottom: 15px;">
                    <div class="metric-title"><?= htmlspecialchars($disk['filesystem']) ?> → <?= htmlspecialchars($disk['mounted'] ?? 'N/A') ?></div>
                    <div class="stat-row">
                        <span class="stat-label">Size:</span>
                        <span class="stat-value"><?= $disk['size'] ?? formatBytes($disk['total']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Used / Available:</span>
                        <span class="stat-value"><?= $disk['used'] ?? formatBytes($disk['used']) ?> / <?= $disk['available'] ?? formatBytes($disk['free']) ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $disk['use_percent'] > 90 ? 'danger' : ($disk['use_percent'] > 80 ? 'warning' : '') ?>"
                             style="width: <?= $disk['use_percent'] ?>%">
                            <?= $disk['use_percent'] ?>%
                        </div>
                    </div>
                    <?php if (isset($disk['inode_use_percent'])): ?>
                    <div class="stat-row" style="margin-top: 8px;">
                        <span class="stat-label">Inode Usage:</span>
                        <span class="stat-value"><?= $disk['inode_use_percent'] ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- OPcache Status -->
            <?php if ($opcache): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        ⚡
                    </div>
                    <div class="card-title">OPcache Status</div>
                </div>
                <?php if ($opcache['enabled'] && $opcache['status']): ?>
                    <?php
                    $status = $opcache['status'];
                    $memUsed = $status['memory_usage']['used_memory'] ?? 0;
                    $memFree = $status['memory_usage']['free_memory'] ?? 0;
                    $memTotal = $memUsed + $memFree;
                    $memPercent = $memTotal > 0 ? ($memUsed / $memTotal) * 100 : 0;
                    ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge success">Enabled</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Cache Full:</span>
                        <span class="stat-value"><?= $status['cache_full'] ? 'Yes' : 'No' ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Cached Scripts:</span>
                        <span class="stat-value"><?= number_format($status['opcache_statistics']['num_cached_scripts'] ?? 0) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Hit Rate:</span>
                        <span class="stat-value">
                            <?php
                            $hits = $status['opcache_statistics']['hits'] ?? 0;
                            $misses = $status['opcache_statistics']['misses'] ?? 0;
                            $total = $hits + $misses;
                            $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;
                            echo number_format($hitRate, 2) . '%';
                            ?>
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Memory Usage:</span>
                        <span class="stat-value"><?= formatBytes($memUsed) ?> / <?= formatBytes($memTotal) ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $memPercent > 90 ? 'danger' : ($memPercent > 75 ? 'warning' : '') ?>"
                             style="width: <?= $memPercent ?>%">
                            <?= number_format($memPercent, 1) ?>%
                        </div>
                    </div>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Disabled</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- APCu Cache -->
            <?php if ($apcu && $apcu['enabled']): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        🗄️
                    </div>
                    <div class="card-title">APCu Cache</div>
                </div>
                <?php
                $apcuInfo = $apcu['info'];
                $sma = $apcu['sma'];
                $memUsed = $sma['seg_size'] - $sma['avail_mem'];
                $memPercent = ($memUsed / $sma['seg_size']) * 100;
                ?>
                <div class="stat-row">
                    <span class="stat-label">Status:</span>
                    <span class="badge success">Enabled</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Cached Keys:</span>
                    <span class="stat-value"><?= number_format($apcuInfo['num_entries'] ?? 0) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Hits:</span>
                    <span class="stat-value"><?= number_format($apcuInfo['num_hits'] ?? 0) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Misses:</span>
                    <span class="stat-value"><?= number_format($apcuInfo['num_misses'] ?? 0) ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Memory Usage:</span>
                    <span class="stat-value"><?= formatBytes($memUsed) ?> / <?= formatBytes($sma['seg_size']) ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $memPercent > 90 ? 'danger' : ($memPercent > 75 ? 'warning' : '') ?>"
                         style="width: <?= $memPercent ?>%">
                        <?= number_format($memPercent, 1) ?>%
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Redis Cache -->
            <?php if ($redis): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">
                        🔴
                    </div>
                    <div class="card-title">Redis Cache</div>
                </div>
                <?php if ($redis['connected']): ?>
                    <?php if (isset($redis['error'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Status:</span>
                            <span class="badge error">Error</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Message:</span>
                            <span class="stat-value"><?= htmlspecialchars($redis['error']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="stat-row">
                            <span class="stat-label">Status:</span>
                            <span class="badge success">Connected</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Connection:</span>
                            <span class="stat-value"><?= htmlspecialchars($redis['connection_type']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Version:</span>
                            <span class="stat-value"><?= htmlspecialchars($redis['version']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Uptime:</span>
                            <span class="stat-value"><?= gmdate('H:i:s', $redis['uptime']) ?> (<?= number_format($redis['uptime'] / 86400, 1) ?> days)</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Total Keys:</span>
                            <span class="stat-value"><?= number_format($redis['total_keys']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Hit Rate:</span>
                            <span class="stat-value"><?= number_format($redis['hit_rate'], 2) ?>%</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Hits / Misses:</span>
                            <span class="stat-value"><?= number_format($redis['hits']) ?> / <?= number_format($redis['misses']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Connected Clients:</span>
                            <span class="stat-value"><?= number_format($redis['connected_clients']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Evicted Keys:</span>
                            <span class="stat-value"><?= number_format($redis['evicted_keys']) ?></span>
                        </div>
                        <?php if ($redis['max_memory'] > 0): ?>
                            <?php
                            $memPercent = ($redis['used_memory'] / $redis['max_memory']) * 100;
                            ?>
                            <div class="stat-row">
                                <span class="stat-label">Memory Usage:</span>
                                <span class="stat-value"><?= formatBytes($redis['used_memory']) ?> / <?= formatBytes($redis['max_memory']) ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $memPercent > 90 ? 'danger' : ($memPercent > 75 ? 'warning' : '') ?>"
                                     style="width: <?= $memPercent ?>%">
                                    <?= number_format($memPercent, 1) ?>%
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="stat-row">
                                <span class="stat-label">Memory Usage:</span>
                                <span class="stat-value"><?= formatBytes($redis['used_memory']) ?> (no limit)</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Not Connected</span>
                    </div>
                    <?php if (isset($redis['error'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Error:</span>
                            <span class="stat-value"><?= htmlspecialchars($redis['error']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Memcached Cache -->
            <?php if ($memcached): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
                        🗃️
                    </div>
                    <div class="card-title">Memcached Cache</div>
                </div>
                <?php if ($memcached['connected']): ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge success">Connected</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Extension:</span>
                        <span class="stat-value"><?= htmlspecialchars($memcached['extension']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Connection:</span>
                        <span class="stat-value"><?= htmlspecialchars($memcached['connection']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($memcached['version']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Uptime:</span>
                        <span class="stat-value"><?= gmdate('H:i:s', $memcached['uptime']) ?> (<?= number_format($memcached['uptime'] / 86400, 1) ?> days)</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Current Items:</span>
                        <span class="stat-value"><?= number_format($memcached['curr_items']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Hit Rate:</span>
                        <span class="stat-value"><?= number_format($memcached['hit_rate'], 2) ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Hits / Misses:</span>
                        <span class="stat-value"><?= number_format($memcached['get_hits']) ?> / <?= number_format($memcached['get_misses']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Evictions:</span>
                        <span class="stat-value"><?= number_format($memcached['evictions']) ?></span>
                    </div>
                    <?php if ($memcached['limit_maxbytes'] > 0): ?>
                        <?php $memPercent = ($memcached['bytes'] / $memcached['limit_maxbytes']) * 100; ?>
                        <div class="stat-row">
                            <span class="stat-label">Memory Usage:</span>
                            <span class="stat-value"><?= formatBytes($memcached['bytes']) ?> / <?= formatBytes($memcached['limit_maxbytes']) ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill <?= $memPercent > 90 ? 'danger' : ($memPercent > 75 ? 'warning' : '') ?>"
                                 style="width: <?= $memPercent ?>%">
                                <?= number_format($memPercent, 1) ?>%
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Not Connected</span>
                    </div>
                    <?php if (isset($memcached['error'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Error:</span>
                            <span class="stat-value"><?= htmlspecialchars($memcached['error']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- MySQL/MariaDB -->
            <?php if ($mysql): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                        🐬
                    </div>
                    <div class="card-title"><?= ($mysql['connected'] && isset($mysql['is_mariadb']) && $mysql['is_mariadb']) ? 'MariaDB' : 'MySQL' ?></div>
                </div>
                <?php if ($mysql['connected']): ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge success">Connected</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Connection:</span>
                        <span class="stat-value"><?= htmlspecialchars($mysql['connection']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($mysql['version']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Uptime:</span>
                        <span class="stat-value"><?= gmdate('H:i:s', $mysql['uptime']) ?> (<?= number_format($mysql['uptime'] / 86400, 1) ?> days)</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Connected Threads:</span>
                        <span class="stat-value"><?= number_format($mysql['threads_connected']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Questions:</span>
                        <span class="stat-value"><?= number_format($mysql['questions']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Data Sent / Received:</span>
                        <span class="stat-value"><?= formatBytes($mysql['bytes_sent']) ?> / <?= formatBytes($mysql['bytes_received']) ?></span>
                    </div>
                    <?php if (isset($mysql['innodb_buffer_pool_size'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">InnoDB Buffer Pool:</span>
                            <span class="stat-value"><?= formatBytes($mysql['innodb_buffer_pool_size']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Not Connected</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- PostgreSQL -->
            <?php if ($postgresql): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);">
                        🐘
                    </div>
                    <div class="card-title">PostgreSQL</div>
                </div>
                <?php if ($postgresql['connected']): ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge success">Connected</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Server Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($postgresql['server_version']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Client Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($postgresql['client_version']) ?></span>
                    </div>
                    <?php if (isset($postgresql['database_count'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Databases:</span>
                            <span class="stat-value"><?= number_format($postgresql['database_count']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($postgresql['active_connections'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Active Connections:</span>
                            <span class="stat-value"><?= number_format($postgresql['active_connections']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Not Connected</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- MongoDB -->
            <?php if ($mongodb): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        🍃
                    </div>
                    <div class="card-title">MongoDB</div>
                </div>
                <?php if ($mongodb['connected']): ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge success">Connected</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Connection:</span>
                        <span class="stat-value"><?= htmlspecialchars($mongodb['connection']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($mongodb['version']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Uptime:</span>
                        <span class="stat-value"><?= gmdate('H:i:s', $mongodb['uptime']) ?> (<?= number_format($mongodb['uptime'] / 86400, 1) ?> days)</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Connections:</span>
                        <span class="stat-value"><?= number_format($mongodb['connections']) ?></span>
                    </div>
                    <?php if (isset($mongodb['opcounters'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Operations:</span>
                            <span class="stat-value">
                                Insert: <?= number_format($mongodb['opcounters']['insert']) ?> | 
                                Query: <?= number_format($mongodb['opcounters']['query']) ?> | 
                                Update: <?= number_format($mongodb['opcounters']['update']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="stat-label">Status:</span>
                        <span class="badge error">Not Connected</span>
                    </div>
                    <?php if (isset($mongodb['error'])): ?>
                        <div class="stat-row">
                            <span class="stat-label">Error:</span>
                            <span class="stat-value"><?= htmlspecialchars($mongodb['error']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- PDO Connections -->
            <?php if ($pdo): ?>
            <div class="card full-width">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);">
                        🔌
                    </div>
                    <div class="card-title">PDO Connections</div>
                </div>
                <div class="grid" style="padding: 0;">
                    <?php foreach ($pdo as $driver): ?>
                    <div class="card" style="margin: 20px;">
                        <div class="metric-box">
                            <div class="metric-title">
                                <?php
                                $driverNames = [
                                    'mysql' => 'MySQL (PDO)',
                                    'pgsql' => 'PostgreSQL (PDO)',
                                    'sqlite' => 'SQLite (PDO)',
                                    'oci' => 'Oracle (PDO)',
                                    'sqlsrv' => 'SQL Server (PDO)',
                                ];
                                echo $driverNames[$driver['driver']] ?? strtoupper($driver['driver']) . ' (PDO)';
                                ?>
                            </div>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Driver:</span>
                            <span class="stat-value"><?= htmlspecialchars($driver['driver']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Status:</span>
                            <?php if ($driver['connected']): ?>
                                <span class="badge success">Connected</span>
                            <?php else: ?>
                                <span class="badge error">Not Connected</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($driver['connected']): ?>
                            <?php if (isset($driver['connection'])): ?>
                                <div class="stat-row">
                                    <span class="stat-label">Connection:</span>
                                    <span class="stat-value"><?= htmlspecialchars($driver['connection']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($driver['version'])): ?>
                                <div class="stat-row">
                                    <span class="stat-label">Version:</span>
                                    <span class="stat-value">
                                        <?= htmlspecialchars($driver['version']) ?>
                                        <?php if (isset($driver['is_mariadb']) && $driver['is_mariadb']): ?>
                                            <span class="badge success" style="margin-left: 10px;">MariaDB</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (isset($driver['error'])): ?>
                            <div class="stat-row">
                                <span class="stat-label">Error:</span>
                                <span class="stat-value"><?= htmlspecialchars($driver['error']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- SQLite3 Extension -->
            <?php if ($sqlite3): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                        📦
                    </div>
                    <div class="card-title">SQLite3 Extension</div>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Status:</span>
                    <span class="badge success">Enabled</span>
                </div>
                <?php if (isset($sqlite3['version'])): ?>
                    <div class="stat-row">
                        <span class="stat-label">SQLite Version:</span>
                        <span class="stat-value"><?= htmlspecialchars($sqlite3['version']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Version Number:</span>
                        <span class="stat-value"><?= htmlspecialchars($sqlite3['version_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($sqlite3['error'])): ?>
                    <div class="stat-row">
                        <span class="stat-label">Error:</span>
                        <span class="stat-value"><?= htmlspecialchars($sqlite3['error']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PHP Extensions -->
        <div class="card full-width">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);">
                    🧩
                </div>
                <div class="card-title">PHP Extensions (<?= count(get_loaded_extensions()) ?>)</div>
            </div>
            <?php foreach ($extensions as $category => $exts): ?>
                <?php if (!empty($exts)): ?>
                <div class="category-section">
                    <div class="category-title"><?= $category ?> (<?= count($exts) ?>)</div>
                    <div class="extension-grid">
                        <?php foreach ($exts as $ext): ?>
                        <div class="extension-item"><?= $ext ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Important PHP Directives -->
        <div class="card full-width">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                    ⚙️
                </div>
                <div class="card-title">PHP Configuration</div>
            </div>
            <div class="grid">
                <?php foreach ($directives as $category => $dirs): ?>
                <div>
                    <div class="category-title"><?= $category ?></div>
                    <?php foreach ($dirs as $directive): ?>
                        <?php $value = ini_get($directive); ?>
                        <div class="stat-row">
                            <span class="stat-label"><?= $directive ?>:</span>
                            <span class="stat-value">
                                <?php if ($value === '' || $value === false): ?>
                                    <span class="badge error">Not set</span>
                                <?php elseif ($value === '1' || $value === 'On'): ?>
                                    <span class="badge success">On</span>
                                <?php elseif ($value === '0' || $value === 'Off'): ?>
                                    <span class="badge warning">Off</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($value) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Configuration Files -->
        <div class="card full-width">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
                    📄
                </div>
                <div class="card-title">Configuration Files</div>
            </div>
            <?php if (isset($configFiles['main'])): ?>
            <div class="metric-box">
                <div class="metric-title">Loaded php.ini</div>
                <div class="code-block"><?= htmlspecialchars($configFiles['main']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (isset($configFiles['additional'])): ?>
            <div class="metric-box">
                <div class="metric-title">Additional .ini Files (<?= count($configFiles['additional']) ?>)</div>
                <div class="code-block">
                    <?php foreach ($configFiles['additional'] as $file): ?>
                        <?= htmlspecialchars(trim($file)) ?><br>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="card full-width" style="text-align: center; padding: 20px;">
            <p style="color: #6b7280; margin-bottom: 10px;">
                Generated at <?= date('Y-m-d H:i:s') ?> |
                Script execution time: <?= round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) ?> ms
            </p>
            <p style="color: #9ca3af; font-size: 0.9em;">
                ⚠️ Security Warning: This tool contains sensitive information. Restrict access in production environments!
            </p>
        </div>
    </div>
</body>
</html>
