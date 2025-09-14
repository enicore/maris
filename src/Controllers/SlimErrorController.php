<?php
namespace Enicore\Maris\Controllers;

use Slim\Exception\HttpException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SlimErrorController extends Controller
{
    public function __invoke(Request $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors,
                             bool $logErrorDetails): Response
    {
        $code = $exception instanceof HttpException && $exception->getCode() > 0 ? $exception->getCode() : 500;

        if ($code == 500) {
            error_log($exception);
        }

        return $this->sendError(new \Slim\Psr7\Response(), $code, $code === 404 ? 'Page not found' : 'Error');
    }
}