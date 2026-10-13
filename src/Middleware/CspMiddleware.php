<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Sets the Content-Security-Policy header, unless the route's response already has one. The default policy allows
 * only the site's own files: no inline scripts or styles, no frames.
 *
 * Alpine's standard build evaluates its expressions with new Function(), which needs 'unsafe-eval' in script-src;
 * Alpine's CSP build doesn't.
 *
 * $app->add(new CspMiddleware("default-src 'self'; script-src 'self' 'unsafe-eval'; ..."));
 *
 * @package Enicore\Maris
 */
final class CspMiddleware implements MiddlewareInterface
{
    public const DEFAULT_POLICY = "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self'; " .
        "img-src 'self' data:; " .
        "font-src 'self'; " .
        "connect-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none';";

    private readonly string $policy;

    /**
     * @param string $policy The policy; the default policy if empty.
     */
    public function __construct(string $policy = '')
    {
        $this->policy = trim($policy) ?: self::DEFAULT_POLICY;
    }

    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);

        return $response->hasHeader('Content-Security-Policy')
            ? $response
            : $response->withHeader('Content-Security-Policy', $this->policy);
    }
}
