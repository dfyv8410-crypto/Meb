<?php
/**
 * MEB config — loads database + app config.
 * DB credentials are read from the installer-generated config file
 * (config/database.php), NEVER hardcoded here.
 */

declare(strict_types=1);

define('MEB_ROOT', dirname(__DIR__));
define('MEB_CONFIG_DIR', __DIR__);
define('MEB_DATA_DIR', MEB_ROOT . '/storage');
define('MEB_UPLOADS_DIR', MEB_ROOT . '/uploads');

// Installer lock path (same relative location as Node original: storage/…)
define('MEB_LOCK_FILE', MEB_DATA_DIR . '/installed.lock');
if (!is_dir(MEB_DATA_DIR)) {
    @mkdir(MEB_DATA_DIR, 0777, true);
}
if (!is_dir(MEB_UPLOADS_DIR)) {
    @mkdir(MEB_UPLOADS_DIR, 0777, true);
}

// App config (jwt secret etc.). Installed by the installer, may not exist yet.
$CFG = [];
$cfgFile = MEB_CONFIG_DIR . '/app.php';
if (is_file($cfgFile)) {
    $CFG = (array) require $cfgFile;
}
define('MEB_JWT_SECRET', $CFG['jwt_secret'] ?? bin2hex(random_bytes(24)));
define('MEB_BASE_URL', $CFG['base_url'] ?? '');
