<?php
/**
 * MEB — FTP deployment helper for Sweb shared hosting.
 *
 * Uploads the CONTENTS of the project root (index.php, .htaccess, config/,
 * api/, installer/, ...) into the remote web root — NOT into any subfolder.
 * Use from a machine whose IP is allowed by Sweb FTP (the sandbox where
 * opencode runs is IP-blocked by Sweb, so run this on your own computer).
 *
 * Usage:
 *   php ftp-upload.php
 *
 * Edit the CONFIG section below first.
 */

declare(strict_types=1);

/* ---------------------------- CONFIG ---------------------------- */
// FTP credentials: set via environment variables or edit below.
$FTP_HOST = getenv('FTP_HOST') ?: '77.222.40.65';
$FTP_PORT = (int) (getenv('FTP_PORT') ?: 21);
$FTP_USER = getenv('FTP_USER') ?: 'artemverev';
$FTP_PASS = getenv('FTP_PASS') ?: '';
// Remote web root. Start with '/' if your FTP login lands at the account root
// and the web root is reached via a path like /home/a/artemverev/godnayamebel.ru/...
// Adjust to where index.php must live.
$REMOTE_ROOT = '/home/a/artemverev';
// Local project root (its CONTENTS are uploaded, not the dir itself).
$LOCAL_SWEB = basename(__DIR__) === 'scripts' ? dirname(__DIR__) : '';
if (!is_dir($LOCAL_SWEB)) { fwrite(STDERR, "Local project root not found. Run from repo root (`php scripts/ftp-upload.php`) or fix \$LOCAL_SWEB.\n"); exit(1); }
// Directories the installer needs write access to (per SWEB_DEPLOY.md).
$WRITE_DIRS = ['config', 'storage', 'uploads'];
/* ---------------------------------------------------------------- */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$log = function (string $s): void { echo $s . "\n"; };

/* 1) Connect. Try SSL first (Sweb supports explicit FTPS), fall back to plain. */
$conn = null;
try { $conn = @ftp_ssl_connect($FTP_HOST, $FTP_PORT, 30); } catch (\Throwable $e) {}
if (!$conn || @ftp_login($conn, $FTP_USER, $FTP_PASS) === false) {
    $note = '';
    if ($conn) { @ftp_close($conn); $conn = null; }
    $conn = @ftp_connect($FTP_HOST, $FTP_PORT, 30);
    if (!$conn || @ftp_login($conn, $FTP_USER, $FTP_PASS) === false) {
        $last = error_get_last();
        $err  = $last['message'] ?? 'connection/login failed';
        $log("FTP error: $err");
        $log("Sweb FTP blocked this IP? The server closes the connection without a banner.");
        exit(1);
    }
}
$log("Connected as {$FTP_USER}@{$FTP_HOST}:{$FTP_PORT} (".(function_exists('ftp_ssl_login') ? 'ssl/plain' : 'plain').")");
@ftp_pasv($conn, true); // passive: required for most shared-host firewalls
@ftp_set_option($conn, FTP_TIMEOUT_SEC, 60);

/* 2) Ensure remote root exists and cd into it. */
$root = rtrim($REMOTE_ROOT, '/');
if ($root !== '' && !@ftp_chdir($conn, $root)) {
    @ftp_mkdir($conn, $root);
    if (!@ftp_chdir($conn, $root)) { $log("Cannot enter remote root: $root"); exit(1); }
}
$log("Remote root: " . (@ftp_pwd($conn) ?: $root));

/* 3) Recursively upload local project root (LOCAL_SWEB) contents into cwd.
 *    Dirs+files already present are overwritten (correct deployment). */
$count = ['files' => 0, 'dirs' => 0];
$skipPatterns = ['/^\.git$/', '/\/\.git$/'];

function up($conn, string $localDir, string $remoteCwd, array &$count, array $skip): void
{
    $entries = @scandir($localDir);
    if ($entries === false) return;
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        if (in_array($e, ['.git', '.svn'], true)) continue;
        $lp = $localDir . '/' . $e;
        $rp = $remoteCwd . '/' . $e;
        if (is_dir($lp)) {
            if (!@ftp_chdir($conn, $rp)) {
                @ftp_mkdir($conn, $e);
                if (!@ftp_chdir($conn, $e)) continue;
            }
            $count['dirs']++;
            up($conn, $lp, $rp, $count, $skip);
            @ftp_chdir($conn, $remoteCwd);
        } else {
            if (@ftp_put($conn, $e, $lp, FTP_BINARY)) {
                $count['files']++;
            } else {
                echo "    WARN put failed: $rp\n";
            }
        }
    }
}
up($conn, rtrim($LOCAL_SWEB, '/'), @ftp_pwd($conn), $count, $skipPatterns);
$log("Uploaded: {$count['files']} files, {$count['dirs']} dirs");

/* 4) Set write permissions on installer-needed dirs (recursively where set). */
foreach ($WRITE_DIRS as $d) {
    if (@ftp_chdir($conn, $d)) {
        @ftp_chmod($conn, 0755, '.');
        @ftp_chdir($conn, '.');
        $c = @ftp_pwd($conn);
        @ftp_close($conn);
        $conn = @ftp_connect($FTP_HOST, $FTP_PORT, 30) ?: @ftp_ssl_connect($FTP_HOST, $FTP_PORT, 30);
        @ftp_login($conn, $FTP_USER, $FTP_PASS);
        @ftp_pasv($conn, true);
        @ftp_chdir($conn, $root);
        @ftp_chdir($conn, $d);
        @ftp_chmod($conn, 0755, '.');
        @ftp_chdir($conn, $root);
        $log("  chmod 755 on /$d (ensure web can write)");
    }
}

@ftp_close($conn);
$log("Done. Open https://ваш-домен/installer in a browser and run the 7-step wizard.");
