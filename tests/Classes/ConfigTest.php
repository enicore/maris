<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Maris\Classes\Config;
use RuntimeException;

#[CoversClass(\Enicore\Maris\Classes\Config::class)]
class ConfigTest extends TestCase
{
    private string $tempDir;
    private string $scriptFileName;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ConfigTest_' . uniqid();
        mkdir($this->tempDir . '/public', 0777, true);
        mkdir($this->tempDir . '/src');
        $this->scriptFileName = $_SERVER['SCRIPT_FILENAME'];
    }

    protected function tearDown(): void
    {
        foreach (array_keys(getenv()) as $name) {
            str_starts_with($name, 'MARIS_T_') && putenv($name);
        }

        $_SERVER['SCRIPT_FILENAME'] = $this->scriptFileName;
        array_map('unlink', array_filter(glob($this->tempDir . '/src/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
        rmdir($this->tempDir . '/src');
        rmdir($this->tempDir . '/public');
        rmdir($this->tempDir);
    }

    public function testLoadsFile(): void
    {
        $file = $this->tempDir . '/src/test.php';
        file_put_contents($file, "<?php return ['name' => 'maris', 'empty' => null];");
        $config = new Config($file);

        $this->assertSame('maris', $config->get('name'));
        $this->assertTrue($config->has('name'));
        // a key set to null exists and returns null, not the default
        $this->assertTrue($config->has('empty'));
        $this->assertNull($config->get('empty', 'default'));
        $this->assertFalse($config->has('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    public function testLoadsDefaultFile(): void
    {
        // without a file, it loads src/config.php next to the directory of the running script
        file_put_contents($this->tempDir . '/src/config.php', "<?php return ['default' => true];");
        $_SERVER['SCRIPT_FILENAME'] = $this->tempDir . '/public/index.php';

        $this->assertTrue((new Config())->get('default'));
    }

    public function testMissingOrInvalidFile(): void
    {
        $this->assertFalse((new Config($this->tempDir . '/src/missing.php'))->has('name'));

        $file = $this->tempDir . '/src/invalid.php';
        file_put_contents($file, "<?php return 'not an array';");
        $log = ini_set('error_log', '/dev/null');
        $config = new Config($file);
        ini_set('error_log', $log);
        $this->assertFalse($config->has('name'));
    }

    public function testSet(): void
    {
        $config = new Config($this->tempDir . '/src/missing.php');
        $config->set('name', 'value');
        $this->assertSame('value', $config->get('name'));
    }

    public function testWithoutFile(): void
    {
        // null loads no file, not even the default one
        file_put_contents($this->tempDir . '/src/config.php', "<?php return ['default' => true];");
        $_SERVER['SCRIPT_FILENAME'] = $this->tempDir . '/public/index.php';

        $this->assertSame([], (new Config(null))->all());
    }

    public function testFromEnv(): void
    {
        putenv('MARIS_T_HOST=db.local');
        putenv('MARIS_T_PORT= 3307 ');
        putenv('MARIS_T_RATE=0.5');
        putenv('MARIS_T_DEBUG=yes');
        putenv('OTHER_NAME=other');

        $config = Config::fromEnv([
            'maris_t_host' => 'string',
            'maris_t_port' => ['type' => 'int', 'default' => 3306],
            'maris_t_rate' => 'float',
            'maris_t_debug' => 'bool',
            'maris_t_missing' => ['type' => 'bool', 'default' => false],
            'key' => ['env' => 'OTHER_NAME'],
        ]);
        putenv('OTHER_NAME');

        $this->assertSame(['maris_t_host' => 'db.local', 'maris_t_port' => 3307, 'maris_t_rate' => 0.5,
            'maris_t_debug' => true, 'maris_t_missing' => false, 'key' => 'other'], $config->all());
    }

    public function testEnvErrors(): void
    {
        putenv('MARIS_T_PORT=abc');
        putenv('MARIS_T_EMPTY=  ');
        putenv('MARIS_T_DEBUG=maybe');

        try {
            Config::fromEnv(['maris_t_port' => 'int', 'maris_t_missing' => 'string', 'maris_t_empty' => 'string',
                'maris_t_optional' => ['type' => 'int', 'default' => null], 'maris_t_debug' => 'bool']);
            $this->fail('No exception for invalid values.');
        } catch (RuntimeException $e) {
            // all the errors at once
            $this->assertSame(
                'Invalid configuration: invalid MARIS_T_PORT (expected int), missing MARIS_T_MISSING, ' .
                'missing MARIS_T_EMPTY, invalid MARIS_T_DEBUG (expected bool)',
                $e->getMessage()
            );
        }

        $this->expectExceptionMessage('Invalid config type for maris_t_port: integer');
        Config::fromEnv(['maris_t_port' => 'integer']);
    }

    public function testLoadEnvOverridesFile(): void
    {
        $file = $this->tempDir . '/src/test.php';
        file_put_contents($file, "<?php return ['maris_t_host' => 'file', 'other' => 'file'];");
        putenv('MARIS_T_HOST=env');

        $config = (new Config($file))->loadEnv(['maris_t_host' => 'string']);
        $this->assertSame(['maris_t_host' => 'env', 'other' => 'file'], $config->all());
    }

    public function testEnvFile(): void
    {
        $file = $this->tempDir . '/src/.env';
        file_put_contents($file, implode("\n", [
            '# comment',
            'MARIS_T_A=plain value # comment',
            'export MARIS_T_B="double # not a comment\tquoted"',
            "MARIS_T_C='single \\t quoted'",
            ' MARIS_T_D = spaced ',
            'MARIS_T_E=',
            'MARIS_T_F=a=b',
            'MARIS_T_SERVER=from file',
            'invalid line',
            '=no name',
        ]));
        // the server's environment takes precedence
        putenv('MARIS_T_SERVER=from server');

        $this->assertTrue(Config::loadEnvFile($file));
        $this->assertSame('plain value', getenv('MARIS_T_A'));
        $this->assertSame("double # not a comment\tquoted", getenv('MARIS_T_B'));
        $this->assertSame('single \\t quoted', getenv('MARIS_T_C'));
        $this->assertSame('spaced', getenv('MARIS_T_D'));
        $this->assertSame('', getenv('MARIS_T_E'));
        $this->assertSame('a=b', getenv('MARIS_T_F'));
        $this->assertSame('from server', getenv('MARIS_T_SERVER'));

        $this->assertFalse(Config::loadEnvFile($this->tempDir . '/src/missing'));
    }

    public function testFromEnvWithFile(): void
    {
        file_put_contents($this->tempDir . '/src/.env', "MARIS_T_HOST=db.local\n");

        $this->assertSame('db.local', Config::fromEnv(['maris_t_host' => 'string'], $this->tempDir . '/src/.env')
            ->get('maris_t_host'));
    }
}
