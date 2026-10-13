<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\Session;
use Enicore\Maris\Classes\View;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Maris\Classes\View::class)]
class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(__DIR__ . '/../Fixtures/app/src/views/');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testViewExists(): void
    {
        $this->assertTrue($this->view->viewExists('home'));
        $this->assertTrue($this->view->viewExists('pages/about'));
        $this->assertFalse($this->view->viewExists('missing'));

        // the path works with or without the trailing slash
        $this->assertTrue((new View(__DIR__ . '/../Fixtures/app/src/views'))->viewExists('home'));
    }

    public function testDefaultPath(): void
    {
        // without a path, it uses src/views next to the directory of the running script
        $scriptFileName = $_SERVER['SCRIPT_FILENAME'];
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../Fixtures/app/src/index.php';
        $view = new View();
        $_SERVER['SCRIPT_FILENAME'] = $scriptFileName;

        $this->assertTrue($view->viewExists('home'));
    }

    public function testGetViewHtml(): void
    {
        $this->assertSame("<home>A &amp; B</home>\n", $this->view->getViewHtml('home', ['title' => 'A & B']));
        // an empty name renders the home view
        $this->assertSame("<home></home>\n", $this->view->getViewHtml(''));
        $this->assertSame("<about>pages/about</about>\n", $this->view->getViewHtml('/pages/about'));
        // data can't overwrite the view's own variables
        $this->assertSame("<about>pages/about</about>\n", $this->view->getViewHtml('pages/about', ['view' => 'x']));
    }

    public function testGetViewHtmlNotFound(): void
    {
        $error = "<error code=\"404\">Page not found (338119)</error>\n";
        $this->assertSame($error, $this->view->getViewHtml('missing'));
        // no access outside the view directory
        $this->assertSame($error, $this->view->getViewHtml('../views/home'));
        $this->assertSame($error, $this->view->getViewHtml('..\\Classes/ViewTest'));
    }

    public function testGetLayoutHtml(): void
    {
        $token = Session::getCsrfToken();
        $html = $this->view->getLayoutHtml('home', ['title' => 'Title']);

        // the data is available in the layout and the view
        $this->assertSame("<layout csrf=\"$token\" title=\"Title\"><home>Title</home>\n</layout>\n", $html);
    }

    public function testGetLayoutHtmlWithoutSession(): void
    {
        // pages outside the session routes don't start a session
        $this->assertSame("<layout csrf=\"\" title=\"\"></layout>\n", $this->view->getLayoutHtml(null));
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testGetLayoutHtmlWithoutContent(): void
    {
        $token = Session::getCsrfToken();

        $this->assertSame("<layout csrf=\"$token\" title=\"\"></layout>\n", $this->view->getLayoutHtml(null));
        $this->assertSame("<admin csrf=\"$token\"><home></home>\n</admin>\n",
            $this->view->getLayoutHtml('home', [], 'admin/layout'));
    }

    public function testViewErrorsDiscardOutput(): void
    {
        $level = ob_get_level();

        try {
            $this->view->getViewHtml('home', ['title' => new \stdClass()]);
            $this->fail('No exception for a view error.');
        } catch (\TypeError) {
        }

        // the output buffer of the failed view is closed
        $this->assertSame($level, ob_get_level());
    }
}
