<?php
namespace Tests\Support;

use Enicore\Maris\Standalone\Database;
use PHPUnit\Framework\TestCase;

/**
 * Connects to the test database set in the MARIS_TEST_DB_* environment variables (see README.md).
 */
class MySql
{
    public static function connect(TestCase $test): Database
    {
        if (!getenv('MARIS_TEST_DB_HOST')) {
            $test->markTestSkipped('MARIS_TEST_DB_HOST is not set.');
        }

        return new Database(
            getenv('MARIS_TEST_DB_HOST'),
            getenv('MARIS_TEST_DB_PORT') ?: 3306,
            getenv('MARIS_TEST_DB_NAME') ?: 'maris_test',
            getenv('MARIS_TEST_DB_USER') ?: 'root',
            (string)getenv('MARIS_TEST_DB_PASSWORD')
        );
    }
}
