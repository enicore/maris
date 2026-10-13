<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Maris\Middleware\AuthMiddleware::class)]
class AuthMiddlewareTest extends TestCase
{
    private function process(bool $loggedIn, string $login = '/login', bool $json = false,
                             ?Handler $handler = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/admin');
        $json && ($request = $request->withHeader('X-Requested-With', 'XMLHttpRequest'));
        $middleware = new AuthMiddleware(fn(ServerRequestInterface $request) => $loggedIn, $login);

        return $middleware->process($request, $handler ?? new Handler());
    }

    public function testLoggedIn(): void
    {
        $handler = new Handler();
        $response = $this->process(true, handler: $handler);

        $this->assertSame('handled', (string)$response->getBody());
        $this->assertSame('/admin', $handler->request->getUri()->getPath());
    }

    public function testRedirectsToLogin(): void
    {
        $response = $this->process(false);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testJsonError(): void
    {
        $response = $this->process(false, json: true);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Please log in.","redirect":"/login"}',
            (string)$response->getBody());
    }

    public function testRouteName(): void
    {
        // in an app, so the request has the route parser
        $app = AppFactory::create();
        $app->setBasePath('/app');
        $app->get('/sign-in', fn($request, $response) => $response)->setName('login');
        $app->get('/admin', fn($request, $response) => $response)
            ->add(new AuthMiddleware(fn() => false, 'login'));

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/app/admin'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/app/sign-in', $response->getHeaderLine('Location'));
    }
}
