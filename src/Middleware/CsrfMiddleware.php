<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Middleware;

use Enicore\Maris\Classes\Http;
use Enicore\Maris\Classes\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Checks the CSRF token of requests that can change data (all but GET, HEAD and OPTIONS) against the session's
 * (Session::getCsrfToken()). The token comes in the X-CSRF-Token header (RavenSpa sends the one in
 * <meta name="csrf">), or in the "csrf" field of posted forms. Requests without a valid token get a 403 error, as
 * JSON if they expect JSON.
 *
 * @package Enicore\Maris
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const HEADER = 'X-CSRF-Token';
    public const FIELD = 'csrf';

    public function process(Request $request, Handler $handler): Response
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $sent = $request->getHeaderLine(self::HEADER);

        if ($sent === '') {
            $body = $request->getParsedBody();
            $sent = is_array($body) && is_string($body[self::FIELD] ?? null) ? $body[self::FIELD] : '';
        }

        $stored = Session::get('csrf');

        if (!is_string($stored) || $stored === '' || $sent === '' || !hash_equals($stored, $sent)) {
            $response = new \Slim\Psr7\Response();

            if (Http::wantsJson($request)) {
                return Http::error($response, 'Invalid CSRF token. Please reload the page.', 403);
            }

            $response->getBody()->write('Invalid CSRF token. Please reload the page.');
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $handler->handle($request);
    }
}
