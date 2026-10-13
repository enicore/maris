<?php
namespace Tests\Functions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the global helper functions in src/Functions/web.php.
 */
#[CoversClass(\Enicore\Maris\Classes\Web::class)]
class WebFunctionsTest extends TestCase
{
    private string $scriptFileName;
    private string $version;

    protected function setUp(): void
    {
        // the asset paths are relative to the directory of the running script
        $this->scriptFileName = $_SERVER['SCRIPT_FILENAME'];
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $this->version = '?v=' . md5((string)filemtime(__DIR__ . '/../Fixtures/response.php'));
    }

    protected function tearDown(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = $this->scriptFileName;
    }

    public function testFunctions(): void
    {
        $this->assertSame("/response.php$this->version", w_url('/response.php'));
        $this->assertSame("<script src='response.php$this->version' defer></script>",
            w_script('response.php', ['defer' => true]));
        $this->assertSame("<link rel='stylesheet' href='/response.php$this->version'>", w_style('/response.php'));
        $this->assertSame("<link rel='icon' href='/response.php$this->version' type='image/png'>",
            w_icon('/response.php', ['type' => 'image/png']));
        $this->assertSame("<img src='/response.php$this->version' alt='A &amp; B'>",
            w_image('/response.php', ['alt' => 'A & B']));
    }
}
