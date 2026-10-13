<?php
namespace Tests\Support;

use Enicore\Maris\Classes\Input;
use Enicore\Maris\Classes\InputException;
use Enicore\Maris\Controllers\Controller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A site's controller for SlimAppTest.
 */
class PageController extends Controller
{
    public function home(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'home', ['title' => 'Welcome'], 'Home');
    }

    public function save(Request $request, Response $response): Response
    {
        try {
            $name = Input::fromBody($request)->getString('name', 10, allowEmpty: false);
        } catch (InputException $e) {
            return $this->sendError($response, $e->getMessage(), 400);
        }

        return $this->sendSuccess($response, ['name' => $name]);
    }

    public function fail(): Response
    {
        throw new \LogicException('Bug 55');
    }
}
