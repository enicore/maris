<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Middleware\HostRestrictionMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Maris\Middleware\HostRestrictionMiddleware::class)]
class HostRestrictionMiddlewareTest extends TestCase
{
    private function process(string $uri, array $serverParams = []): string
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri, $serverParams);
        $middleware = new HostRestrictionMiddleware(['example.com', 'WWW.Example.com']);
        $response = $middleware->process($request, new Handler());

        return $response->getStatusCode() . ' ' . $response->getBody();
    }

    public function testHostHeader(): void
    {
        $this->assertSame('200 handled', $this->process('/', ['HTTP_HOST' => 'example.com']));
        $this->assertSame('200 handled', $this->process('/', ['HTTP_HOST' => 'Example.COM']));
        $this->assertSame('200 handled', $this->process('/', ['HTTP_HOST' => 'www.example.com']));
        $this->assertSame('200 handled', $this->process('/', ['HTTP_HOST' => 'example.com:8080']));
        $this->assertSame('404 Not Found', $this->process('/', ['HTTP_HOST' => 'user.cpanel.example.com']));
        $this->assertSame('404 Not Found', $this->process('/', ['HTTP_HOST' => 'example.com.evil.com']));
        // the header takes precedence over the uri
        $this->assertSame('404 Not Found', $this->process('http://example.com/', ['HTTP_HOST' => 'other.com']));
    }

    public function testUriHost(): void
    {
        $this->assertSame('200 handled', $this->process('http://example.com:8080/'));
        $this->assertSame('404 Not Found', $this->process('http://other.com/'));
    }
}
