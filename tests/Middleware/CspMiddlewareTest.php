<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Middleware\CspMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Maris\Middleware\CspMiddleware::class)]
class CspMiddlewareTest extends TestCase
{
    private function process(CspMiddleware $middleware, ?RequestHandlerInterface $handler = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        return $middleware->process($request, $handler ?? new Handler());
    }

    public function testDefault(): void
    {
        $response = $this->process(new CspMiddleware());

        $this->assertSame('handled', (string)$response->getBody());
        $this->assertSame(CspMiddleware::DEFAULT_POLICY, $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", CspMiddleware::DEFAULT_POLICY);
        $this->assertSame(CspMiddleware::DEFAULT_POLICY,
            $this->process(new CspMiddleware('  '))->getHeaderLine('Content-Security-Policy'));
    }

    public function testPolicy(): void
    {
        $response = $this->process(new CspMiddleware("  default-src 'none';  "));
        $this->assertSame("default-src 'none';", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testKeepsRoutePolicy(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withHeader('Content-Security-Policy', "default-src *");
            }
        };

        $this->assertSame('default-src *',
            $this->process(new CspMiddleware(), $handler)->getHeaderLine('Content-Security-Policy'));
    }
}
