<?php
namespace Enicore\Maris\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Enicore\Maris\Classes\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        // only enforce on non-safe routes
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $sent = $request->getHeaderLine('X-CSRF-Token');
            $stored = Session::get('csrf');

            if (!$stored || !$sent || !hash_equals($stored, $sent)) {
                $response = (new Response())
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'text/html; charset=utf-8');
                $response->getBody()->write('Invalid CSRF token');
                return $response;
            }
        }

        return $handler->handle($request);
    }
}
