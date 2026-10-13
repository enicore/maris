<?php /** @noinspection ALL */

namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Maris\Classes\Web;

#[CoversClass(\Enicore\Maris\Classes\Web::class)]
class WebTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT'], $_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testGetHost(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_NAME'] = 'example.com';
        $this->assertSame('https://example.com', Web::getHost());

        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http://example.com', Web::getHost());
    }

    public function testGetUrl(): void
    {
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test/page';
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://example.com/test/page/', Web::getUrl());

        $this->assertSame('https://example.com/test/page/path', Web::getUrl('/path'));
        $this->assertSame('example.com', Web::getUrl('', true));
    }

    public function testCreatePermalink(): void
    {
        $this->assertSame('hello-world', Web::createPermalink('Hello World!'));
        $this->assertSame('test-url', Web::createPermalink('Test URL?'));
        $this->assertFalse(Web::createPermalink('   '));
    }

    public function testGetBrowserLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9,fr;q=0.8';
        $this->assertSame('en-us', Web::getBrowserLanguage());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertSame('en', Web::getBrowserLanguage());
    }

    public function testValidateEmail(): void
    {
        $this->assertTrue(Web::validateEmail('test@example.com', false));
        $this->assertFalse(Web::validateEmail('invalid-email', false));
        $this->assertTrue(Web::validateEmail('test@example.com')); // Assuming DNS exists for example.com
    }

    public function testGetUrlHostAndPort(): void
    {
        $_SERVER['SERVER_NAME'] = 'server.com';
        $_SERVER['REQUEST_URI'] = '/app/index.php?a=1';
        // the script name is removed from the path
        $this->assertSame('http://server.com/app/', Web::getUrl());

        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '8080';
        $this->assertSame('http://example.com:8080/app/', Web::getUrl());

        // the Host header includes the port when it isn't the default one
        $_SERVER['HTTP_HOST'] = 'example.com:8080';
        $this->assertSame('http://example.com:8080/app/', Web::getUrl());
        $this->assertSame('example.com:8080', Web::getUrl('', true));

        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://example.com/app/', Web::getUrl());

        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $this->assertFalse(Web::getUrl());
    }

    public function testGetUrlProxy(): void
    {
        $_SERVER['HTTP_HOST'] = 'internal';
        $_SERVER['SERVER_PORT'] = '8080';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'example.com';
        $_SERVER['HTTP_X_FORWARDED_PORT'] = '443';

        $this->assertSame('https://example.com/path', Web::getUrl('path'));
        $this->assertSame('https://cdn.com/file', Web::getUrl('//cdn.com/file'));
    }

    public function testGetUrlAbsoluteAndCut(): void
    {
        $this->assertSame('https://other.com/x', Web::getUrl('https://other.com/x'));

        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/app/admin/users/';
        $this->assertSame('http://example.com/app/', Web::getUrl('', false, 'admin/'));
        $this->assertSame('http://example.com/app/x', Web::getUrl('x', false, ['missing', 'admin/']));
        $this->assertSame('http://example.com/app/admin/users/', Web::getUrl('', false, 'missing'));
    }

    public function testGetBrowserLanguageQuality(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr;q=0.5,DE;q=0.9,en;q=0.1';
        $this->assertSame('de', Web::getBrowserLanguage());
    }

    public function testVersionedUrl(): void
    {
        // the asset paths are relative to the directory of the running script
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $version = md5((string)filemtime(__DIR__ . '/../Fixtures/response.php'));
        $this->assertSame("/response.php?v=$version", Web::versionedUrl('/response.php'));
        $this->assertSame("response.php?v=$version", Web::versionedUrl('response.php'));

        // a missing file is logged and has no version
        $log = tempnam(sys_get_temp_dir(), 'maris');
        $previous = ini_set('error_log', $log);
        $this->assertSame('/missing.js', Web::versionedUrl('/missing.js'));
        ini_set('error_log', $previous);
        $this->assertStringContainsString('Asset not found (381995): /missing.js', file_get_contents($log));
        unlink($log);
    }

    public function testTags(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/index.php';
        $url = '/response.php?v=' . md5((string)filemtime(__DIR__ . '/../Fixtures/response.php'));

        $this->assertSame("<script src='$url' defer></script>",
            Web::scriptTag('/response.php', ['defer' => true, 'async' => false, 'nonce' => null]));
        $this->assertSame("<link rel='stylesheet' href='$url' media='print'>",
            Web::styleTag('/response.php', ['media' => 'print']));
        $this->assertSame("<link rel='icon' href='$url' sizes='32'>", Web::iconTag('/response.php', ['sizes' => 32]));
        $this->assertSame("<img src='$url' alt='&#039;&gt;&lt;script&gt;'>",
            Web::imageTag('/response.php', ['alt' => "'><script>"]));

        // the path is escaped too
        $previous = ini_set('error_log', '/dev/null');
        $this->assertSame("<img src='x&#039; onerror=&#039;a'>", Web::imageTag("x' onerror='a"));
        ini_set('error_log', $previous);
    }
}
