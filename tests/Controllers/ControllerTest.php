<?php
namespace Tests\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Session;
use Enicore\Maris\Classes\View;
use Enicore\Maris\Controllers\Controller;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

#[CoversClass(\Enicore\Maris\Controllers\Controller::class)]
#[CoversClass(\Enicore\Maris\Classes\Http::class)]
class ControllerTest extends TestCase
{
    private Controller $controller;
    private string $token;

    protected function setUp(): void
    {
        $this->controller = $this->controller();
        $this->token = Session::getCsrfToken();
    }

    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Returns a controller that exposes the protected methods.
     */
    private function controller(string $layout = 'layout'): Controller
    {
        $controller = new class(new View(__DIR__ . '/../Fixtures/app/src/views')) extends Controller {
            public function call(string $method, mixed ...$args): ResponseInterface
            {
                return $this->$method(new Response(), ...$args);
            }

            public function page(ServerRequestInterface $request, mixed ...$args): ResponseInterface
            {
                return $this->sendPage($request, new Response(), ...$args);
            }

            public function setLayout(string $layout): void
            {
                $this->layout = $layout;
            }

            public function setViewDirectory(string $directory): void
            {
                $this->viewDirectory = $directory;
            }
        };
        $controller->setLayout($layout);

        return $controller;
    }

    private function request(string $method): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, '/');
    }

    private function layout(string $content, string $title = ''): string
    {
        return "<layout csrf=\"$this->token\" title=\"$title\">$content</layout>\n";
    }

    private function json(ResponseInterface $response): array
    {
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        return json_decode((string)$response->getBody(), true);
    }

    public function testLayoutRoute(): void
    {
        $response = $this->controller->layoutRoute($this->request('GET'), new Response(), ['path' => 'pages/about']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        // RavenSpa loads the content
        $this->assertSame($this->layout(''), (string)$response->getBody());

        $response = $this->controller('admin/layout')->layoutRoute($this->request('GET'), new Response(), []);
        $this->assertSame("<admin csrf=\"$this->token\"></admin>\n", (string)$response->getBody());
    }

    public function testSendPage(): void
    {
        // RavenSpa's request
        $response = $this->controller->page($this->request('POST'), 'home', ['title' => 'A'], 'Home');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true,
                'data' => ['title' => 'Home', 'html' => "<home>A</home>\n", 'data' => ['title' => 'A']]],
            $this->json($response)
        );

        // a GET request gets the whole page
        $response = $this->controller->page($this->request('GET'), 'home', [], 'Home', 201);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame($this->layout("<home>Home</home>\n", 'Home'), (string)$response->getBody());

        // the view gets the title in RavenSpa's requests too
        $response = $this->controller->page($this->request('POST'), 'home', [], 'Home');
        $this->assertSame("<home>Home</home>\n", $this->json($response)['data']['html']);

        $response = $this->controller('admin/layout')->page($this->request('GET'), 'home');
        $this->assertSame("<admin csrf=\"$this->token\"><home></home>\n</admin>\n", (string)$response->getBody());
    }

    public function testStaticPageRoute(): void
    {
        $response = $this->controller->staticPageRoute($this->request('POST'), new Response(),
            ['path' => '/pages/about/']);
        $this->assertSame("<about>pages/about</about>\n", $this->json($response)['data']['html']);

        $response = $this->controller->staticPageRoute($this->request('GET'), new Response(), []);
        $this->assertSame($this->layout("<home></home>\n"), (string)$response->getBody());

        // the views of an area
        $this->controller->setViewDirectory('/pages/');
        $response = $this->controller->staticPageRoute($this->request('POST'), new Response(), ['path' => 'about']);
        $this->assertSame("<about>pages/about</about>\n", $this->json($response)['data']['html']);
        $response = $this->controller->staticPageRoute($this->request('POST'), new Response(), []);
        $this->assertSame('Page not found', $this->json($response)['data']['title']);
        $this->controller->setViewDirectory('');

        // a missing view is the 404 page
        $response = $this->controller->staticPageRoute($this->request('GET'), new Response(), ['path' => 'missing']);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"404\">Page not found</error>\n", 'Page not found'),
            (string)$response->getBody());
    }

    public function testNotFoundRoute(): void
    {
        $response = $this->controller->notFoundRoute($this->request('GET'), new Response(), []);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"404\">Page not found</error>\n", 'Page not found'),
            (string)$response->getBody());

        // RavenSpa shows the error view as the page's content (it shows only an error message for HTTP errors)
        $response = $this->controller->notFoundRoute($this->request('POST'), new Response(), []);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['title' => 'Page not found', 'html' => "<error code=\"404\">Page not found</error>\n",
                'data' => ['code' => 404, 'message' => 'Page not found']],
            $this->json($response)['data']
        );
    }

    public function testSendView(): void
    {
        $response = $this->controller->call('sendView', 'home', ['title' => 'Login'], 401);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame("<home>Login</home>\n", (string)$response->getBody());
    }

    public function testSendSuccessAndError(): void
    {
        $response = $this->controller->call('sendSuccess', ['a' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => true, 'data' => ['a' => 1]], $this->json($response));

        $this->assertSame(['success' => true, 'data' => []], $this->json($this->controller->call('sendSuccess')));
        $this->assertSame(201, $this->controller->call('sendSuccess', [], 201)->getStatusCode());

        $response = $this->controller->call('sendError', 'Failed');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Failed'], $this->json($response));

        $response = $this->controller->call('sendError', 'Not allowed', 403, ['redirect' => '/login']);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Not allowed', 'redirect' => '/login'],
            $this->json($response));
    }

    public function testSendJson(): void
    {
        $response = $this->controller->call('sendJson', ['url' => 'a/b', 'text' => 'ä', 'invalid' => "\xff"], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"url":"a/b","text":"ä","invalid":"' . "\u{fffd}" . '"}', (string)$response->getBody());

        // values that can't be encoded send a JSON error, and are logged
        $previous = ini_set('error_log', '/dev/null');
        $response = $this->controller->call('sendJson', ['number' => INF]);
        ini_set('error_log', $previous);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'JSON encoding failed (384992)'], $this->json($response));
    }

    public function testSendHtmlTextAndRedirect(): void
    {
        $response = $this->controller->call('sendHtml', '<b>html</b>', 202);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<b>html</b>', (string)$response->getBody());

        $response = $this->controller->call('sendText', 'text');
        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('text', (string)$response->getBody());

        $response = $this->controller->call('sendRedirect', '/login');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertSame(301, $this->controller->call('sendRedirect', '/new', 301)->getStatusCode());
    }

    public function testSendErrorPage(): void
    {
        $response = $this->controller->call('sendErrorPage', 404);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"404\">Page not found.</error>\n", 'Page not found.'),
            (string)$response->getBody());

        $response = $this->controller->call('sendErrorPage', 500);
        $this->assertSame($this->layout("<error code=\"500\">Server error.</error>\n", 'Server error.'),
            (string)$response->getBody());

        $response = $this->controller->call('sendErrorPage', 403, 'Forbidden');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame($this->layout("<error code=\"403\">Forbidden</error>\n", 'Forbidden'),
            (string)$response->getBody());
    }
}
