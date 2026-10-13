<?php
namespace Tests\Integration;

use Enicore\Maris\Classes\Session;
use Enicore\Maris\Classes\View;
use Enicore\Maris\Controllers\Controller;
use Enicore\Maris\Controllers\SlimErrorController;
use Enicore\Maris\Middleware\AuthMiddleware;
use Enicore\Maris\Middleware\CspMiddleware;
use Enicore\Maris\Middleware\CsrfMiddleware;
use Enicore\Maris\Middleware\SecurityHeadersMiddleware;
use Enicore\Maris\Middleware\SessionMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\PageController;
use Tests\Support\TestContainer;

/**
 * An SPA app set up like the sites, to test that the controllers and middleware work together.
 */
#[CoversClass(Controller::class)]
#[CoversClass(SlimErrorController::class)]
#[CoversClass(AuthMiddleware::class)]
#[CoversClass(CsrfMiddleware::class)]
#[CoversClass(SessionMiddleware::class)]
#[CoversClass(SecurityHeadersMiddleware::class)]
#[CoversClass(CspMiddleware::class)]
class SlimAppTest extends TestCase
{
    private App $app;
    private bool $loggedIn = true;

    protected function setUp(): void
    {
        $view = new View(__DIR__ . '/../Fixtures/app/src/views');
        $container = TestContainer::instance();
        $container->set(View::class, $view);
        $container->set(Controller::class, new Controller($view));
        $container->set(PageController::class, new PageController($view));
        $container->set(SlimErrorController::class, new SlimErrorController($view));

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(false, false, false)->setDefaultErrorHandler(SlimErrorController::class);
        // added after the error middleware, so they run around it and the error responses get the headers too
        $this->app->add(new CspMiddleware());
        $this->app->add(new SecurityHeadersMiddleware());

        $this->app->get('/login', fn($request, $response) => $response)->setName('login');
        $this->app->group('', function ($group) {
            $group->post('/', [PageController::class, 'home']);
            $group->post('/save', [PageController::class, 'save']);
            $group->post('/fail', [PageController::class, 'fail']);
            $group->post('/{path:.+}', [Controller::class, 'staticPageRoute']);
            $group->get('/{path:.*}', [Controller::class, 'layoutRoute']);
        })
            ->add(new CsrfMiddleware())
            ->add(new AuthMiddleware(fn() => $this->loggedIn, 'login'))
            ->add(new SessionMiddleware());
    }

    protected function tearDown(): void
    {
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
        TestContainer::instance()->clear();
    }

    /**
     * Sends a request like RavenSpa's (JSON, with the CSRF token) or like a browser's.
     */
    private function request(string $method, string $path, bool $spa = true, ?array $body = null,
                             ?string $token = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, "https://example.com$path");

        if ($spa) {
            $request = $request
                ->withHeader('Accept', 'application/json')
                ->withHeader('X-CSRF-Token', $token ?? Session::getCsrfToken());
        }

        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body));
        }

        return $this->app->handle($request);
    }

    private function json(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true);
    }

    public function testSpaNavigation(): void
    {
        // the layout first
        $response = $this->request('GET', '/about', false);
        $this->assertSame(200, $response->getStatusCode());
        $token = Session::getCsrfToken();
        $this->assertSame("<layout csrf=\"$token\" title=\"\"></layout>\n", (string)$response->getBody());
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringStartsWith("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));

        // then the content
        $response = $this->request('POST', '/');
        $this->assertSame(['success' => true, 'data' => ['title' => 'Home', 'html' => "<home>Welcome</home>\n",
            'data' => ['title' => 'Welcome']]], $this->json($response));

        $data = $this->json($this->request('POST', '/pages/about'))['data'];
        $this->assertSame("<about>pages/about</about>\n", $data['html']);

        $data = $this->json($this->request('POST', '/missing'))['data'];
        $this->assertSame('Page not found', $data['title']);
    }

    public function testActions(): void
    {
        $this->request('GET', '/', false);

        $response = $this->request('POST', '/save', body: ['name' => ' Maris ']);
        $this->assertSame(['success' => true, 'data' => ['name' => 'Maris']], $this->json($response));

        $response = $this->request('POST', '/save', body: ['name' => '']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Missing value: name'], $this->json($response));
    }

    public function testCsrf(): void
    {
        $this->request('GET', '/', false);

        $response = $this->request('POST', '/save', body: ['name' => 'x'], token: 'wrong');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($this->json($response)['success']);
    }

    public function testLoggedOut(): void
    {
        $this->loggedIn = false;

        $response = $this->request('GET', '/', false);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));

        $response = $this->request('POST', '/');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('/login', $this->json($response)['redirect']);
    }

    public function testErrors(): void
    {
        $response = $this->request('PUT', '/', false);
        $this->assertSame(405, $response->getStatusCode());
        $this->assertStringContainsString('<error code="405">Method not allowed.', (string)$response->getBody());
        // the error responses get the headers too
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        // the error page outside the session routes doesn't start a session
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertStringContainsString('csrf=""', (string)$response->getBody());

        $log = tempnam(sys_get_temp_dir(), 'maris');
        $previous = ini_set('error_log', $log);
        $this->request('GET', '/', false);
        $response = $this->request('POST', '/fail');
        ini_set('error_log', $previous);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Server error.'], $this->json($response));
        $this->assertStringContainsString('Bug 55', file_get_contents($log));
        unlink($log);
    }
}
