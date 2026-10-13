<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Middleware;

use Closure;
use Enicore\Maris\Classes\Http;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Routing\RouteContext;

/**
 * Lets only logged-in users through. Others are redirected to the login page, or get a 401 JSON error with the login
 * URL ({"success": false, "error": "...", "redirect": "/login"}) if the request expects JSON.
 *
 * The login check is a function, so it works with any authentication library:
 *
 * $group->add(new AuthMiddleware(fn() => $container->get(Auth::class)->isLoggedIn(), 'login'));
 *
 * @package Enicore\Maris
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private Closure $isLoggedIn;

    /**
     * @param callable $isLoggedIn Returns true if the user is logged in; gets the request.
     * @param string $login The login page: a route name, or a path if it starts with "/".
     */
    public function __construct(callable $isLoggedIn, private readonly string $login = 'login')
    {
        $this->isLoggedIn = Closure::fromCallable($isLoggedIn);
    }

    public function process(Request $request, Handler $handler): Response
    {
        if (($this->isLoggedIn)($request)) {
            return $handler->handle($request);
        }

        $url = str_starts_with($this->login, '/')
            ? $this->login
            : RouteContext::fromRequest($request)->getRouteParser()->urlFor($this->login);
        $response = new \Slim\Psr7\Response();

        if (Http::wantsJson($request)) {
            return Http::error($response, 'Please log in.', 401, ['redirect' => $url]);
        }

        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
