<?php
require __DIR__ . '/../vendor/autoload.php';

use Enicore\Maris\Standalone\Di;
use Tests\Support\TestContainer;

// sessions are started by the tests in the CLI: keep their files out of the system's session directory
$sessionDir = sys_get_temp_dir() . '/maris_test_sessions';
is_dir($sessionDir) || mkdir($sessionDir);
ini_set('session.save_path', $sessionDir);

// the container can be set only once, so all tests share one and replace its services
Di::setContainer(TestContainer::instance());
