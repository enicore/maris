<?php
namespace Tests\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\View;
use Enicore\Maris\Controllers\SlimErrorController;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(\Enicore\Maris\Controllers\SlimErrorController::class)]
class SlimErrorControllerTest extends TestCase
{
    private SlimErrorController $controller;
    private string $log = '';
    private string $previousLog = '';

    protected function setUp(): void
    {
        $this->controller = new SlimErrorController(new View(__DIR__ . '/../Fixtures/app/src/views'));
    }

    protected function tearDown(): void
    {
        if ($this->log) {
            ini_set('error_log', $this->previousLog);
            unlink($this->log);
        }
    }

    /**
     * Sends error_log() to a file (PHPUnit sets its own error_log after setUp()).
     */
    private function startLog(): void
    {
        $this->log = tempnam(sys_get_temp_dir(), 'maris');
        $this->previousLog = (string)ini_set('error_log', $this->log);
    }

    private function request(bool $json = false): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        return $json ? $request->withHeader('Accept', 'application/json') : $request;
    }

    private function page(int $code, string $message): string
    {
        // no session is active, so no CSRF token
        return "<layout csrf=\"\" title=\"$message\">" .
            "<error code=\"$code\">$message</error>\n</layout>\n";
    }

    public function testHttpErrors(): void
    {
        $this->startLog();
        $request = $this->request();

        $response = ($this->controller)($request, new HttpNotFoundException($request), false, false, false);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->page(404, 'Not found.'), (string)$response->getBody());

        $exception = new HttpMethodNotAllowedException($request);
        $response = ($this->controller)($request, $exception, false, false, false);
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame($this->page(405, 'Method not allowed.'), (string)$response->getBody());

        $response = ($this->controller)($request, new HttpException($request, 'Teapot', 418), false, false, false);
        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame($this->page(418, 'Teapot'), (string)$response->getBody());

        // HTTP errors aren't logged
        $this->assertSame('', file_get_contents($this->log));
    }

    public function testJsonErrors(): void
    {
        $this->startLog();
        $request = $this->request(true);

        $response = ($this->controller)($request, new HttpNotFoundException($request), false, false, false);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Not found."}', (string)$response->getBody());

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/')
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        $response = ($this->controller)($request, new Exception('Failure 77'), false, false, false);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Server error."}', (string)$response->getBody());
    }

    public function testServerErrors(): void
    {
        $this->startLog();
        $response = ($this->controller)($this->request(), new Exception('Failure 77'), false, false, false);

        // the message is logged, not shown
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($this->page(500, 'Server error.'), (string)$response->getBody());
        $this->assertStringContainsString('Failure 77', file_get_contents($this->log));

        // unless the details are displayed (development)
        $response = ($this->controller)($this->request(true), new Exception('Failure 78'), true, false, false);
        $this->assertSame('{"success":false,"error":"Server error. Failure 78"}', (string)$response->getBody());
    }
}
