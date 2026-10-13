<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Session;
use Enicore\Maris\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Maris\Middleware\SessionMiddleware::class)]
class SessionMiddlewareTest extends TestCase
{
    private array $options;

    protected function setUp(): void
    {
        $this->options = Session::getOptions();
    }

    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
        Session::setOptions($this->options);
    }

    public function testStartsSession(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = (new SessionMiddleware())->process($request, new Handler());

        $this->assertSame('handled', (string)$response->getBody());
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('Lax', session_get_cookie_params()['samesite']);
    }

    public function testOptions(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        (new SessionMiddleware(['samesite' => 'Strict']))->process($request, new Handler());

        $this->assertSame('Strict', session_get_cookie_params()['samesite']);
    }
}
