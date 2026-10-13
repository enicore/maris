<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Middleware;

use Enicore\Maris\Classes\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Starts the session, with the given cookie options (see Session::setOptions()). Add it to the routes that need a
 * session only, so other requests (APIs, webhooks) don't create sessions. Slim runs the middleware added last first,
 * so add it after the middleware that use the session (CsrfMiddleware, AuthMiddleware).
 *
 * @package Enicore\Maris
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly array $options = [])
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        if ($this->options) {
            Session::setOptions($this->options);
        }

        Session::start();

        return $handler->handle($request);
    }
}
