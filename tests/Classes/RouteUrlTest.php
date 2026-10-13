<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Maris\Classes\RouteUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\UriFactory;

#[CoversClass(\Enicore\Maris\Classes\RouteUrl::class)]
class RouteUrlTest extends TestCase
{
    public function testUrls(): void
    {
        $app = AppFactory::create();
        $app->setBasePath('/app');
        $app->get('/users/{id}', fn($request, $response) => $response)->setName('user');

        $routeUrl = new RouteUrl();
        $routeUrl->setRouteParser($app->getRouteCollector()->getRouteParser());

        $this->assertSame('/app/users/5', $routeUrl->get('user', ['id' => 5]));
        $this->assertSame('/app/users/5?tab=a+b', $routeUrl->get('user', ['id' => 5], ['tab' => 'a b']));
        $this->assertSame('https://example.com/app/users/5',
            $routeUrl->getFull((new UriFactory())->createUri('https://example.com/other'), 'user', ['id' => 5]));
    }

    public function testWithoutParser(): void
    {
        $this->expectException(RuntimeException::class);
        (new RouteUrl())->get('user');
    }
}
