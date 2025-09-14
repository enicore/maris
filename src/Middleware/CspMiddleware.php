<?php
namespace Enicore\Maris\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Enicore\Maris\Classes\Di;

final class CspMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $csp = Di::config()->get('csp', '');
        if (!is_string($csp)) {
            error_log("Invalid CSP config value (377288)");
            $csp = '';
        } else {
            $csp = trim($csp);
        }

        if (empty($csp)) {
            $csp = "default-src 'self'; ".
                "script-src 'self'; ".
                "style-src 'self'; ". // style-src 'self' 'unsafe-inline'; -- if you want to enable inline styles
                "img-src 'self' data:; ".
                "font-src 'self'; ".
                "connect-src 'self'; ".
                "object-src 'none';";
        }
        return $handler->handle($request)->withHeader('Content-Security-Policy', $csp);
    }
}
