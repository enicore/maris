<?php /** @noinspection ALL */

namespace Tests\Utils;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Utils\Web;

class WebTest extends TestCase
{
    /**
     * @covers \Enicore\Maris\Utils\Web::getHost
     */
    public function testGetHost(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_NAME'] = 'example.com';
        $this->assertSame('https://example.com', Web::getHost());

        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http://example.com', Web::getHost());
    }

    /**
     * @covers \Enicore\Maris\Utils\Web::getUrl
     */
    public function testGetUrl(): void
    {
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test/page';
        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://example.com/test/page/', Web::getUrl());

        $this->assertSame('https://example.com/test/page/path', Web::getUrl('/path'));
        $this->assertSame('example.com', Web::getUrl('', true));
    }

    /**
     * @covers \Enicore\Maris\Utils\Web::createPermalink
     */
    public function testCreatePermalink(): void
    {
        $this->assertSame('hello-world', Web::createPermalink('Hello World!'));
        $this->assertSame('test-url', Web::createPermalink('Test URL?'));
        $this->assertFalse(Web::createPermalink('   '));
    }

    /**
     * @covers \Enicore\Maris\Utils\Web::getBrowserLanguage
     */
    public function testGetBrowserLanguage(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9,fr;q=0.8';
        $this->assertSame('en-us', Web::getBrowserLanguage());

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertSame('en', Web::getBrowserLanguage());
    }

    /**
     * @covers \Enicore\Maris\Utils\Web::validateEmail
     */
    public function testValidateEmail(): void
    {
        $this->assertTrue(Web::validateEmail('test@example.com', false));
        $this->assertFalse(Web::validateEmail('invalid-email', false));
        $this->assertTrue(Web::validateEmail('test@example.com')); // Assuming DNS exists for example.com
    }
}
