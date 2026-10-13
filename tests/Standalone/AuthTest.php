<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Standalone\Auth;
use Enicore\Maris\Classes\Session;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Maris\Standalone\Auth::class)]
class AuthTest extends TestCase
{
    private Auth $auth;

    protected function setUp(): void
    {
        $this->auth = new Auth(new Session());
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testLoggedOut(): void
    {
        $this->assertFalse($this->auth->isLoggedIn());
        $this->assertNull($this->auth->getUserData());
        $this->assertNull($this->auth->getUserId());
        $this->assertNull($this->auth->get('name'));
    }

    public function testSetUserData(): void
    {
        Session::start();
        $id = session_id();
        $this->auth->setUserData(['userId' => 5, 'name' => 'Maris']);

        // the session id changes at login (session fixation)
        $this->assertNotSame($id, session_id());
        $this->assertTrue($this->auth->isLoggedIn());
        $this->assertSame(['userId' => 5, 'name' => 'Maris'], $this->auth->getUserData());
        $this->assertSame(5, $this->auth->getUserId());
        $this->assertSame('Maris', $this->auth->get('name'));
        $this->assertNull($this->auth->get('missing'));
        $this->assertSame(['userId' => 5, 'name' => 'Maris'], Session::get('auth_user_data'));

        $id = session_id();
        $this->auth->setUserData(['userId' => 6], false);
        $this->assertSame($id, session_id());
        $this->assertSame(6, $this->auth->getUserId());
    }

    public function testCustomSessionKey(): void
    {
        (new Auth(new Session(), 'admin'))->setUserData(['userId' => 1]);

        $this->assertSame(['userId' => 1], Session::get('admin'));
        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testSetUserDataRequiresUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->auth->setUserData(['name' => 'Maris']);
    }

    public function testRemoveUserData(): void
    {
        $this->auth->setUserData(['userId' => 5]);
        Session::set('other', 'value');
        $this->auth->removeUserData(false);

        $this->assertFalse($this->auth->isLoggedIn());
        $this->assertSame('value', Session::get('other'));

        $this->auth->setUserData(['userId' => 5]);
        $this->auth->removeUserData();
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertFalse($this->auth->isLoggedIn());
        $this->assertNull(Session::get('other'));
    }
}
