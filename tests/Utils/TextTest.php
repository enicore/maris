<?php
namespace Tests\Utils;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Utils\Text;

/**
 * @coversDefaultClass \Enicore\Maris\Utils\Text
 */
class TextTest extends TestCase
{
    /**
     * @covers ::sizeToString
     */
    public function testSizeToString(): void
    {
        $this->assertSame('1 B', Text::sizeToString(1));
        $this->assertSame('1.5 MB', Text::sizeToString(1500000));
        $this->assertSame('1 GB', Text::sizeToString(1000000000));
        $this->assertSame('-', Text::sizeToString('invalid'));
    }

    /**
     * @covers ::shortenString
     */
    public function testShortenString(): void
    {
        $this->assertSame('Short text', Text::shortenString('Short text', 20));
        $this->assertSame('This is a long...', Text::shortenString('This is a long string that exceeds the limit', 17));
        $this->assertSame('Edge-case...', Text::shortenString('Edge-case test', 10));
    }

    /**
     * @covers ::removeSlash
     */
    public function testRemoveSlash(): void
    {
        $this->assertSame('path/to/resource', Text::removeSlash('path/to/resource/'));
        $this->assertSame('path\\to\\resource', Text::removeSlash('path\\to\\resource\\'));
        $this->assertSame('no-slash', Text::removeSlash('no-slash'));
    }

    /**
     * @covers ::addSlash
     */
    public function testAddSlash(): void
    {
        $this->assertSame('path/to/resource/', Text::addSlash('path/to/resource'));
        $this->assertSame('path\\to\\resource/', Text::addSlash('path\\to\\resource\\'));
        $this->assertSame('already/has/slash/', Text::addSlash('already/has/slash/'));
    }

    /**
     * @covers ::strToBool
     */
    public function testStrToBool(): void
    {
        $this->assertTrue(Text::strToBool('true'));
        $this->assertFalse(Text::strToBool('false'));
        $this->assertFalse(Text::strToBool('invalid'));
        $this->assertFalse(Text::strToBool(''));
        $this->assertTrue(Text::strToBool('  true  ')); // Trimmed input
    }
}
