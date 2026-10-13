<?php
namespace Tests\Standalone;

use PHPUnit\Framework\Attributes\CoversClass;
use BadMethodCallException;
use Enicore\Maris\Classes\Config;
use Enicore\Maris\Standalone\Di;
use Enicore\Maris\Standalone\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\TestContainer;

#[CoversClass(\Enicore\Maris\Standalone\Di::class)]
class DiTest extends TestCase
{
    private array $services;

    protected function setUp(): void
    {
        $this->services = (new ReflectionProperty(Di::class, 'services'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Di::class, 'services'))->setValue(null, $this->services);
        (new ReflectionProperty(Di::class, 'container'))->setValue(null, TestContainer::instance());
        TestContainer::instance()->clear();
    }

    public function testGet(): void
    {
        $config = new Config();
        TestContainer::instance()->set(Config::class, $config);

        $this->assertSame($config, Di::get('config'));
        $this->assertSame($config, Di::config());
    }

    public function testGetUnknownService(): void
    {
        $this->assertFalse(Di::has('unknown'));
        $this->expectException(BadMethodCallException::class);
        Di::get('unknown');
    }

    public function testGetServiceMissingInContainer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot retrieve service 'request'");
        Di::request();
    }

    public function testGetWithoutContainer(): void
    {
        (new ReflectionProperty(Di::class, 'container'))->setValue(null, null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Container not initialized');
        Di::get('config');
    }

    public function testSetContainerOnlyOnce(): void
    {
        $this->expectException(RuntimeException::class);
        Di::setContainer(new TestContainer());
    }

    public function testSetContainer(): void
    {
        (new ReflectionProperty(Di::class, 'container'))->setValue(null, null);
        $container = new TestContainer();
        $request = new Request();
        $container->set(Request::class, $request);
        Di::setContainer($container);

        $this->assertSame($request, Di::request());
    }

    public function testAdd(): void
    {
        $service = new \ArrayObject();
        TestContainer::instance()->set(\ArrayObject::class, $service);
        Di::add('list', \ArrayObject::class);

        $this->assertTrue(Di::has('list'));
        $this->assertSame($service, Di::list());

        $this->expectException(InvalidArgumentException::class);
        Di::add('missing', 'Missing\Class');
    }
}
