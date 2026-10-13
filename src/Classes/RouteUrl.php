<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use Psr\Http\Message\UriInterface;
use RuntimeException;
use Slim\Interfaces\RouteParserInterface;

/**
 * Builds the URLs of Slim's named routes, so controllers can get them without the request. Slim creates the route
 * parser with the app: set it after AppFactory::create(), on the instance in the container.
 *
 * $container->get(RouteUrl::class)->setRouteParser($app->getRouteCollector()->getRouteParser());
 * ...
 * $url = $this->routeUrl->get('user', ['id' => 5], ['tab' => 'settings']);
 *
 * @package Enicore\Maris
 */
class RouteUrl
{
    private ?RouteParserInterface $routeParser = null;

    /**
     * @param RouteParserInterface $routeParser The app's route parser.
     * @return void
     */
    public function setRouteParser(RouteParserInterface $routeParser): void
    {
        $this->routeParser = $routeParser;
    }

    /**
     * Returns the path of the named route, including the app's base path.
     *
     * @param string $routeName
     * @param array $data The route's placeholder values.
     * @param array $queryParams Values for the query string.
     * @return string
     */
    public function get(string $routeName, array $data = [], array $queryParams = []): string
    {
        return $this->getParser()->urlFor($routeName, $data, $queryParams);
    }

    /**
     * Returns the full URL of the named route, with the scheme and host of the given URI (usually the request's).
     *
     * @param UriInterface $uri
     * @param string $routeName
     * @param array $data The route's placeholder values.
     * @param array $queryParams Values for the query string.
     * @return string
     */
    public function getFull(UriInterface $uri, string $routeName, array $data = [],
                            array $queryParams = []): string
    {
        return $this->getParser()->fullUrlFor($uri, $routeName, $data, $queryParams);
    }

    private function getParser(): RouteParserInterface
    {
        if (!$this->routeParser) {
            throw new RuntimeException("RouteUrl: the route parser isn't set.");
        }

        return $this->routeParser;
    }
}
