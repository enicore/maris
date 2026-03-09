<?php
namespace Enicore\Maris\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Restrict requests by the Host header.
 *
 * This can be used to prevent access to domain via subdomains automatically created by cPanel.
 */
final class HostRestrictionMiddleware implements MiddlewareInterface
{
    public function __construct(private array $allowedDomains) {}

    public function process(Request $request, Handler $handler): Response
    {
        $serverParams = $request->getServerParams();
        $host = strtolower($serverParams['HTTP_HOST'] ?? $request->getUri()->getHost());

        if (!in_array($host, $this->allowedDomains, true)) {
            $response = new \Slim\Psr7\Response();
            $response = $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'text/html; charset=utf-8');
            $response->getBody()->write('Not Found');
            return $response;
        }

        return $handler->handle($request);
    }
}
