<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Middleware;

use Enicore\Maris\Classes\Http;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Sets the common security headers, unless the route's response already has them. Use CspMiddleware for the
 * Content-Security-Policy.
 *
 * Strict-Transport-Security (HSTS) is sent on https requests only: browsers then use https for the site for the given
 * time, so enable it only on sites that will stay on https.
 *
 * $app->add(new SecurityHeadersMiddleware(['X-Frame-Options' => 'SAMEORIGIN'], hstsMaxAge: 31536000));
 *
 * @package Enicore\Maris
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public const DEFAULT_HEADERS = [
        // don't guess file types (an uploaded "image" isn't run as a script)
        'X-Content-Type-Options' => 'nosniff',
        // no framing by other sites (clickjacking), for browsers that don't support CSP's frame-ancestors
        'X-Frame-Options' => 'DENY',
        // send only the origin to other sites, not the full URL
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // a popup opened by another site can't access this site's window
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    private readonly array $headers;

    /**
     * @param array $headers Headers that replace the defaults or are added to them; null or "" removes a default.
     * @param int $hstsMaxAge The Strict-Transport-Security max-age in seconds; 0 doesn't send it.
     */
    public function __construct(array $headers = [], private readonly int $hstsMaxAge = 0)
    {
        $this->headers = array_filter(
            array_merge(self::DEFAULT_HEADERS, $headers),
            fn($value) => $value !== null && $value !== ''
        );
    }

    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);
        $headers = $this->headers;

        if ($this->hstsMaxAge > 0 && Http::isHttps($request)) {
            $headers['Strict-Transport-Security'] = "max-age=$this->hstsMaxAge";
        }

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, (string)$value);
            }
        }

        return $response;
    }
}
