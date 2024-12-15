<?php
namespace Tests\Resources;

use Enicore\Maris\Resources\Countries;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Enicore\Maris\Resources\Countries
 */
class CountriesTest extends TestCase
{
    /**
     * @covers ::getCountries
     */
    public function testGetCountriesReturnsAllCountries(): void
    {
        $countries = Countries::getCountries();
        $this->assertIsArray($countries, 'getCountries should return an array');
        $this->assertNotEmpty($countries, 'getCountries should return a non-empty array');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US in the result');
    }

    /**
     * @covers ::getCountries
     */
    public function testGetCountriesFiltersByCodes(): void
    {
        $countries = Countries::getCountries(['US', 'GB']);
        $this->assertCount(2, $countries, 'getCountries should return only the requested countries');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US');
        $this->assertArrayHasKey('GB', $countries, 'getCountries should include GB');
    }

    /**
     * @covers ::getCountries
     */
    public function testGetCountriesReturnsLocalizedNames(): void
    {
        $countries = Countries::getCountries(['US'], 'es');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US');
        $this->assertEquals('Estados Unidos', $countries['US'], 'getCountries should return the localized name in Spanish');
    }

    /**
     * @covers ::getCodes
     */
    public function testGetCodes(): void
    {
        $codes = Countries::getCodes();
        $this->assertIsArray($codes, 'getCodes should return an array');
        $this->assertContains('US', $codes, 'getCodes should include US');
        $this->assertContains('GB', $codes, 'getCodes should include GB');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameInEnglish(): void
    {
        $name = Countries::getName('US', 'en');
        $this->assertEquals('United States', $name, 'getName should return the country name in English');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameInDifferentLanguage(): void
    {
        $name = Countries::getName('US', 'es');
        $this->assertEquals('Estados Unidos', $name, 'getName should return the country name in Spanish');
    }

    /**
     * @covers ::getName
     */
    public function testGetNameInvalidCode(): void
    {
        $name = Countries::getName('XX');
        $this->assertEquals('', $name, 'getName should return an empty string for an invalid code');
    }

    /**
     * @covers ::codeExists
     */
    public function testCodeExistsValidCode(): void
    {
        $this->assertTrue(Countries::codeExists('US'), 'codeExists should return true for a valid country code');
    }

    /**
     * @covers ::codeExists
     */
    public function testCodeExistsInvalidCode(): void
    {
        $this->assertFalse(Countries::codeExists('XX'), 'codeExists should return false for an invalid country code');
    }
}
