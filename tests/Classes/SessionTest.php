<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Session;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Maris\Classes\Session::class)]
class SessionTest extends TestCase
{
    private array $options;

    protected function setUp(): void
    {
        $this->options = Session::getOptions();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        Session::setOptions($this->options);
        session_name('PHPSESSID');
        session_id('');
    }

    public function testStart(): void
    {
        Session::start(['key' => 'value']);

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame(['key' => 'value'], $_SESSION);
        $params = session_get_cookie_params();
        $this->assertTrue($params['secure']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame(0, $params['lifetime']);
        $this->assertSame('/', $params['path']);

        // an active session isn't replaced
        Session::start(['other' => 'value']);
        $this->assertSame(['key' => 'value'], $_SESSION);
    }

    public function testValues(): void
    {
        $this->assertFalse(Session::has('key'));
        $this->assertSame('default', Session::get('key', 'default'));

        Session::set('key', 'value');
        Session::set('null', null);
        $this->assertTrue(Session::has('key'));
        $this->assertSame('value', Session::get('key'));
        $this->assertTrue(Session::has('null'));

        Session::remove('key');
        $this->assertFalse(Session::has('key'));
    }

    public function testRegenerate(): void
    {
        Session::set('key', 'value');
        $id = session_id();
        Session::regenerate();

        $this->assertNotSame($id, session_id());
        $this->assertSame('value', Session::get('key'));
    }

    public function testDestroy(): void
    {
        Session::set('key', 'value');
        Session::destroy();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame([], $_SESSION);
    }

    public function testGetCsrfToken(): void
    {
        $token = Session::getCsrfToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Session::getCsrfToken());
        $this->assertSame($token, Session::get('csrf'));
    }

    public function testOptions(): void
    {
        Session::setOptions(['name' => 'maris', 'lifetime' => 3600, 'samesite' => 'strict', 'secure' => false]);
        Session::start();

        $this->assertSame('maris', session_name());
        $params = session_get_cookie_params();
        $this->assertSame(3600, $params['lifetime']);
        $this->assertSame('Strict', $params['samesite']);
        $this->assertFalse($params['secure']);
        $this->assertTrue($params['httponly']);
    }

    public function testInvalidOptions(): void
    {
        try {
            Session::setOptions(['samesite' => 'Lax', 'unknown' => 1]);
            $this->fail('No exception for an unknown option.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Unknown session options: unknown', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        Session::setOptions(['samesite' => 'sometimes']);
    }

    public function testRejectsUnknownSessionId(): void
    {
        // strict mode: a session id the server didn't create (session fixation) is replaced
        session_id('attackerchosenid123456');
        Session::start();

        $this->assertNotSame('attackerchosenid123456', session_id());
    }
}
