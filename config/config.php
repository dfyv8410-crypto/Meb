<?php
/**
 * MEB config — loads database + app config.
 * DB credentials are read from the installer-generated config file
 * (config/database.php), NEVER hardcoded here.
 */

declare(strict_types=1);

if (!defined('MEB_ROOT'))       define('MEB_ROOT', dirname(__DIR__));
if (!defined('MEB_CONFIG_DIR')) define('MEB_CONFIG_DIR', __DIR__);
if (!defined('MEB_DATA_DIR'))   define('MEB_DATA_DIR', dirname(__DIR__) . '/storage');
if (!defined('MEB_UPLOADS_DIR')) define('MEB_UPLOADS_DIR', dirname(__DIR__) . '/uploads');
if (!defined('MEB_LOCK_FILE'))  define('MEB_LOCK_FILE', dirname(__DIR__) . '/storage/installed.lock');

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
if (!defined('MEB_JWT_SECRET')) define('MEB_JWT_SECRET', $CFG['jwt_secret'] ?? bin2hex(random_bytes(24)));
if (!defined('MEB_BASE_URL'))   define('MEB_BASE_URL', $CFG['base_url'] ?? '');
