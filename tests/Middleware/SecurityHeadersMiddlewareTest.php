<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Maris\Middleware\SecurityHeadersMiddleware::class)]
#[CoversClass(\Enicore\Maris\Classes\Http::class)]
class SecurityHeadersMiddlewareTest extends TestCase
{
    private function headers(SecurityHeadersMiddleware $middleware, string $uri = 'http://example.com/',
                             array $server = [], ?RequestHandlerInterface $handler = null,
                             array $requestHeaders = []): array
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri, $server);
        foreach ($requestHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return array_map(fn($values) => implode(', ', $values),
            $middleware->process($request, $handler ?? new Handler())->getHeaders());
    }

    public function testDefaults(): void
    {
        $this->assertSame(SecurityHeadersMiddleware::DEFAULT_HEADERS, $this->headers(new SecurityHeadersMiddleware()));
    }

    public function testCustomHeaders(): void
    {
        $headers = $this->headers(new SecurityHeadersMiddleware([
            'X-Frame-Options' => 'SAMEORIGIN',
            'Permissions-Policy' => null,
            'Cross-Origin-Opener-Policy' => '',
            'X-Custom' => 'value',
        ]));

        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('value', $headers['X-Custom']);
        $this->assertArrayNotHasKey('Permissions-Policy', $headers);
        $this->assertArrayNotHasKey('Cross-Origin-Opener-Policy', $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testHsts(): void
    {
        $middleware = new SecurityHeadersMiddleware(hstsMaxAge: 31536000);

        $this->assertSame('max-age=31536000',
            $this->headers($middleware, 'https://example.com/')['Strict-Transport-Security']);
        $this->assertSame('max-age=31536000',
            $this->headers($middleware, server: ['HTTPS' => 'on'])['Strict-Transport-Security']);
        $this->assertSame('max-age=31536000', $this->headers($middleware,
            requestHeaders: ['X-Forwarded-Proto' => 'https'])['Strict-Transport-Security']);

        // not on http, where it would have no effect
        $this->assertArrayNotHasKey('Strict-Transport-Security', $this->headers($middleware));
        $this->assertArrayNotHasKey('Strict-Transport-Security',
            $this->headers($middleware, server: ['HTTPS' => 'off']));
        // not by default
        $this->assertArrayNotHasKey('Strict-Transport-Security',
            $this->headers(new SecurityHeadersMiddleware(), 'https://example.com/'));
    }

    public function testKeepsRouteHeaders(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withHeader('X-Frame-Options', 'SAMEORIGIN');
            }
        };

        $this->assertSame('SAMEORIGIN',
            $this->headers(new SecurityHeadersMiddleware(), handler: $handler)['X-Frame-Options']);
    }
}
