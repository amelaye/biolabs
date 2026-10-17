<?php
/**
 * PHPUnit bootstrap: autoload, per-process test database and attachments, dotenv
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

/*
 * Test isolation: every PHPUnit process gets its own SQLite database and its own attachments
 * directory (var/test/*_<pid>), so that two runs at the same time never share a schema or a file.
 * Both are removed when the process ends. Set before the dotenv files are read, because a variable
 * that is already defined wins over .env.test.
 */
if ('test' === ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null)) {
    $biolabsTestDir = dirname(__DIR__).'/var/test';
    @mkdir($biolabsTestDir, 0777, true);
    $biolabsPid = (string) getmypid();
    $biolabsDatabase = $biolabsTestDir.'/data_'.$biolabsPid.'.db';
    $biolabsAttachments = $biolabsTestDir.'/attachments_'.$biolabsPid;
    foreach (['DATABASE_URL' => 'sqlite:///'.$biolabsDatabase, 'TEST_ATTACHMENTS_DIR' => $biolabsAttachments] as $biolabsName => $biolabsValue) {
        putenv($biolabsName.'='.$biolabsValue);
        $_SERVER[$biolabsName] = $_ENV[$biolabsName] = $biolabsValue;
    }
    register_shutdown_function(static function () use ($biolabsDatabase, $biolabsAttachments): void {
        foreach (glob($biolabsDatabase.'*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($biolabsAttachments.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($biolabsAttachments);
    });
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
