<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

#[CoversClass(\Enicore\Maris\Classes\Http::class)]
class HttpTest extends TestCase
{
    private function request(array $headers = [], string $uri = 'http://example.com/',
                             array $server = []): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $uri, $server);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function testWantsJson(): void
    {
        $this->assertTrue(Http::wantsJson($this->request(['Accept' => 'application/json'])));
        $this->assertTrue(Http::wantsJson($this->request(['Accept' => 'text/html, Application/JSON;q=0.9'])));
        $this->assertTrue(Http::wantsJson($this->request(['X-Requested-With' => 'XMLHttpRequest'])));
        $this->assertFalse(Http::wantsJson($this->request(['Accept' => 'text/html,*/*'])));
        $this->assertFalse(Http::wantsJson($this->request()));
    }

    public function testIsHttps(): void
    {
        $this->assertTrue(Http::isHttps($this->request([], 'https://example.com/')));
        $this->assertTrue(Http::isHttps($this->request([], 'http://example.com/', ['HTTPS' => 'on'])));
        $this->assertTrue(Http::isHttps($this->request(['X-Forwarded-Proto' => 'HTTPS'])));
        $this->assertFalse(Http::isHttps($this->request([], 'http://example.com/', ['HTTPS' => 'off'])));
        $this->assertFalse(Http::isHttps($this->request(['X-Forwarded-Proto' => 'http'])));
        $this->assertFalse(Http::isHttps($this->request()));
    }

    public function testJson(): void
    {
        $response = Http::json(new Response(), ['a' => 'ü/'], 202);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":"ü/"}', (string)$response->getBody());

        $this->assertSame('{"success":true,"data":{"b":2}}',
            (string)Http::success(new Response(), ['b' => 2])->getBody());
        $this->assertSame('{"success":false,"error":"Failed","field":"name"}',
            (string)Http::error(new Response(), 'Failed', 400, ['field' => 'name'])->getBody());
    }
}
