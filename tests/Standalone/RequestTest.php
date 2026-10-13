<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Code;
use Enicore\Maris\Standalone\Request;
use PHPUnit\Framework\TestCase;
use Tests\Support\Server;

#[CoversClass(\Enicore\Maris\Standalone\Request::class)]
class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testReadsGetAndPost(): void
    {
        $_GET = ['a' => 'get', 'b' => 'get'];
        $_POST = ['b' => 'post', 'empty' => null];
        $request = new Request();

        // post values override get values
        $this->assertSame(['a' => 'get', 'b' => 'post', 'empty' => null], $request->all());
        $this->assertSame('get', $request->get('a'));
        $this->assertSame('post', $request->get('b'));
        $this->assertSame('default', $request->get('missing', 'default'));
        $this->assertTrue($request->has('a'));
        $this->assertFalse($request->has('missing'));
        $this->assertFalse($request->has('empty'));
    }

    public function testSet(): void
    {
        $request = new Request();
        $request->set('key', 'value');
        $this->assertSame('value', $request->get('key'));

        $request->setAll(['other' => 1]);
        $this->assertSame(['other' => 1], $request->all());
    }

    public function testGetDecodedId(): void
    {
        $request = new Request();
        $this->assertFalse($request->getDecodedId());

        $request->set('id', Code::encodeId(42));
        $this->assertSame(42, $request->getDecodedId());
    }

    public function testReadsJsonBody(): void
    {
        $server = new Server(__DIR__ . '/../Fixtures/request.php');

        // json values override get values
        $this->assertSame('{"a":"get","b":"json","c":[1]}', $server->get('a=get&b=get', '{"b":"json","c":[1]}')[2]);
        $this->assertSame('{"b":"post"}', $server->get('', 'b=post', 'application/x-www-form-urlencoded')[2]);
        // bodies that aren't a json object or array are ignored
        $this->assertSame('{"a":"get"}', $server->get('a=get', '5')[2]);
        $this->assertSame('{"a":"get"}', $server->get('a=get', 'invalid')[2]);
        $this->assertSame('[]', $server->get('')[2]);
    }
}
