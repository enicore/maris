<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Server;

#[CoversClass(\Enicore\Maris\Standalone\Response::class)]
class ResponseTest extends TestCase
{
    private static ?Server $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new Server(__DIR__ . '/../Fixtures/response.php');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server = null;
    }

    public function testSuccess(): void
    {
        [$code, $headers, $body] = self::$server->get('action=success');
        $this->assertSame(200, $code);
        $this->assertSame('application/json', $headers['content-type']);
        $this->assertSame('{"success":true,"data":{"a":1}}', $body);

        $this->assertSame('{"success":true,"data":[]}', self::$server->get('action=successEmpty')[2]);
    }

    public function testError(): void
    {
        [$code, $headers, $body] = self::$server->get('action=error');
        $this->assertSame(200, $code);
        $this->assertSame('application/json', $headers['content-type']);
        $this->assertSame('{"success":false,"message":"Failed","data":{"b":2}}', $body);
    }

    public function testTerminate(): void
    {
        [$code, $headers, $body] = self::$server->get('action=terminate');
        $this->assertSame(403, $code);
        $this->assertSame('application/json', $headers['content-type']);
        $this->assertStringContainsString('no-store', $headers['cache-control']);
        $this->assertSame('{"error":"Forbidden"}', $body);

        [$code, , $body] = self::$server->get('action=terminateDefault');
        $this->assertSame(404, $code);
        $this->assertSame('{"error":""}', $body);
    }
}
