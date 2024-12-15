<?php
namespace Tests\Resources;

use Enicore\Maris\Resources\Locales;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Enicore\Maris\Resources\Locales
 */
class LocalesTest extends TestCase
{
    /**
     * @covers ::getLocales
     */
    public function testGetLocalesReturnsAllLocales(): void
    {
        $locales = Locales::getLocales();
        $this->assertIsArray($locales, 'getLocales should return an array');
        $this->assertNotEmpty($locales, 'getLocales should return a non-empty array');
        $this->assertArrayHasKey('en_US', $locales, 'getLocales should include English (US)');
        $this->assertArrayHasKey('fr_FR', $locales, 'getLocales should include French (FR)');
    }

    /**
     * @covers ::getLocales
     */
    public function testGetLocalesFiltersByCodes(): void
    {
        $locales = Locales::getLocales(['en_US', 'fr_FR']);
        $this->assertCount(2, $locales, 'getLocales should return only the requested locales');
        $this->assertArrayHasKey('en_US', $locales, 'getLocales should include English (US)');
        $this->assertArrayHasKey('fr_FR', $locales, 'getLocales should include French (FR)');
    }

    /**
     * @covers ::getLocales
     */
    public function testGetLocalesReturnsLocalizedNames(): void
    {
        $locales = Locales::getLocales(['en_US'], true);
        $this->assertArrayHasKey('en_US', $locales, 'getLocales should include English (US)');
        $this->assertEquals('English (US)', $locales['en_US'], 'getLocales should return the localized name');
    }

    /**
     * @covers ::getCodes
     */
    public function testGetCodes(): void
    {
        $codes = Locales::getCodes();
        $this->assertIsArray($codes, 'getCodes should return an array');
        $this->assertContains('en_US', $codes, 'getCodes should include English (US)');
        $this->assertContains('fr_FR', $codes, 'getCodes should include French (FR)');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameInEnglish(): void
    {
        $name = Locales::getName('en_US', false);
        $this->assertEquals('English (United States)', $name, 'getName should return the English name for the locale');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameLocalized(): void
    {
        $name = Locales::getName('fr_FR', true);
        $this->assertEquals('Français (France)', $name, 'getName should return the localized name for the locale');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameInvalidCode(): void
    {
        $name = Locales::getName('xx_XX');
        $this->assertFalse($name, 'getName should return false for an invalid locale code');
    }

    /**
     * @covers ::codeExists
     */
    public function testCodeExistsValidCode(): void
    {
        $this->assertTrue(Locales::codeExists('en_US'), 'codeExists should return true for a valid locale code');
    }

    /**
     * @covers ::codeExists
     */
    public function testCodeExistsInvalidCode(): void
    {
        $this->assertFalse(Locales::codeExists('xx_XX'), 'codeExists should return false for an invalid locale code');
    }
}
