<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PSR-7 request and response helpers shared by the controllers and middleware.
 *
 * The JSON responses follow the format the frontend scripts expect:
 * - success: {"success": true, "data": {...}}
 * - error: {"success": false, "error": "message"}
 *
 * @package Enicore\Maris
 */
class Http
{
    /**
     * Returns true if the request expects a JSON response: fetch() calls that send "Accept: application/json" (like
     * RavenSpa's page loads) or the X-Requested-With header.
     *
     * @param Request $request
     * @return bool
     */
    public static function wantsJson(Request $request): bool
    {
        return stripos($request->getHeaderLine('Accept'), 'application/json') !== false ||
            strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Returns true if the request came over https, directly or through a proxy that sets X-Forwarded-Proto.
     *
     * @param Request $request
     * @return bool
     */
    public static function isHttps(Request $request): bool
    {
        $params = $request->getServerParams();

        return $request->getUri()->getScheme() === 'https' ||
            (!empty($params['HTTPS']) && strtolower($params['HTTPS']) !== 'off') ||
            strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';
    }

    /**
     * Writes the payload as JSON to the response. If the payload can't be encoded, the response is a 500 error.
     *
     * @param Response $response
     * @param array $payload
     * @param int $code The HTTP status code.
     * @return Response
     */
    public static function json(Response $response, array $payload, int $code = 200): Response
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            error_log("JSON encoding failed (384992): " . json_last_error_msg());
            $json = '{"success":false,"error":"JSON encoding failed (384992)"}';
            $code = 500;
        }

        $response->getBody()->write($json);

        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Writes a success JSON response: {"success": true, "data": {...}}.
     *
     * @param Response $response
     * @param array $data
     * @param int $code The HTTP status code.
     * @return Response
     */
    public static function success(Response $response, array $data = [], int $code = 200): Response
    {
        return self::json($response, ['success' => true, 'data' => $data], $code);
    }

    /**
     * Writes an error JSON response: {"success": false, "error": "message"}, plus the extra values.
     *
     * @param Response $response
     * @param string $message
     * @param int $code The HTTP status code.
     * @param array $extra Additional values for the payload, for example ['redirect' => '/login'].
     * @return Response
     */
    public static function error(Response $response, string $message, int $code = 200, array $extra = []): Response
    {
        return self::json($response, ['success' => false, 'error' => $message] + $extra, $code);
    }
}
