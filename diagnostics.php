<?php
/**
 * PHP Server Diagnostics Tool
 * Vizuális diagnosztikai eszköz PHP környezet elemzéséhez
 */

// Biztonsági beállítás - csak localhost-ról engedjük
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    // Éles környezetben kommenteld ki, vagy adj hozzá IP white-list-et
    // die('Access denied. This tool is only accessible from localhost.');
}

/**
 * Formázza a bájtokat olvasható formátumba
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
 * Státusz badge színe
 */
function getStatusColor($value, $type = 'boolean') {
    if ($type === 'boolean') {
        return $value ? '#10b981' : '#ef4444';
    }
    return '#6366f1';
}

/**
 * PHP Extensions lekérése csoportosítva
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
 * OPcache információk
 */
function getOPcacheInfo() {
    if (!function_exists('opcache_get_status')) {
        return null;
    }

    $status = @opcache_get_status(false);
    $config = @opcache_get_configuration();

    return [
        'enabled' => $status !== false,
        'status' => $status,
        'config' => $config
    ];
}

/**
 * APCu információk
 */
function getAPCuInfo() {
    if (!function_exists('apcu_cache_info')) {
        return null;
    }

    $info = @apcu_cache_info(true);
    $sma = @apcu_sma_info(true);

    return [
        'enabled' => $info !== false,
        'info' => $info,
        'sma' => $sma
    ];
}

/**
 * Memória információk
 */
function getMemoryInfo() {
    return [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ];
}

/**
 * PHP konfigurációs fájlok
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
 * Fontos PHP direktívák
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

// Adatok gyűjtése
$phpVersion = phpversion();
$extensions = getExtensionsByCategory();
$opcache = getOPcacheInfo();
$apcu = getAPCuInfo();
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
<html lang="hu">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            color: #1f2937;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .php-version {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 30px;
            border-radius: 50px;
            font-size: 1.3em;
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
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
        }

        .card-title {
            font-size: 1.3em;
            color: #1f2937;
            font-weight: 600;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }

        .stat-value {
            color: #1f2937;
            font-weight: 600;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            color: white;
        }

        .badge.success { background: #10b981; }
        .badge.error { background: #ef4444; }
        .badge.warning { background: #f59e0b; }
        .badge.info { background: #6366f1; }

        .extension-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 15px;
        }

        .extension-item {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            text-align: center;
            color: #374151;
            font-weight: 500;
        }

        .progress-bar {
            background: #e5e7eb;
            height: 25px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85em;
            transition: width 0.3s;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }

        .metric-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .metric-box:last-child {
            margin-bottom: 0;
        }

        .metric-title {
            font-size: 0.85em;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .metric-value {
            font-size: 1.8em;
            color: #1f2937;
            font-weight: 700;
        }

        .code-block {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
            margin-top: 10px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .category-section {
            margin-bottom: 20px;
        }

        .category-title {
            color: #6366f1;
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
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
                ⚠️ Biztonsági figyelmeztetés: Ez az eszköz érzékeny információkat tartalmaz. Éles környezetben korlátozd a hozzáférést!
            </p>
        </div>
    </div>
</body>
</html>
