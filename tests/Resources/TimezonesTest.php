<?php
namespace Tests\Resources;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Resources\Timezones;

/**
 * @covers Timezones
 */
class TimezonesTest extends TestCase
{
    /**
     * @covers Timezones::getTimezones
     */
    public function testGetTimezonesWithDefaultParameters()
    {
        $timezones = Timezones::getTimezones();
        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
    }

    /**
     * @covers Timezones::getTimezones
     */
    public function testGetTimezonesWithExtendedTrue()
    {
        $timezones = Timezones::getTimezones(true);
        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
    }

    /**
     * @covers Timezones::getName
     */
    public function testGetNameWithValidTimezone()
    {
        $timezone = 'Europe/Berlin';
        $localizedName = Timezones::getName($timezone, 'en');

        $this->assertIsString($localizedName);
        $this->assertNotEmpty($localizedName);
    }

    /**
     * @covers Timezones::getName
     */
    public function testGetNameWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $localizedName = Timezones::getName($timezone, 'en');

        $this->assertEquals($timezone, $localizedName);
    }

    /**
     * @covers Timezones::getUtcOffset
     */
    public function testGetUtcOffsetWithValidTimezone()
    {
        $timezone = 'Europe/London';
        $utcOffset = Timezones::getUtcOffset($timezone);

        $this->assertMatchesRegularExpression('/^[+-]\d{2}:\d{2}$/', $utcOffset);
    }

    /**
     * @covers Timezones::getUtcOffset
     */
    public function testGetUtcOffsetWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $utcOffset = Timezones::getUtcOffset($timezone);

        $this->assertEquals('Invalid Time Zone', $utcOffset);
    }

    /**
     * @covers Timezones::timeZoneExists
     */
    public function testTimeZoneExistsWithValidTimezone()
    {
        $timezone = 'America/New_York';
        $exists = Timezones::timeZoneExists($timezone);

        $this->assertTrue($exists);
    }

    /**
     * @covers Timezones::timeZoneExists
     */
    public function testTimeZoneExistsWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $exists = Timezones::timeZoneExists($timezone);

        $this->assertFalse($exists);
    }

    /**
     * @covers Timezones::getTimezoneData
     */
    public function testGetTimezoneDataWithDefaultParameters()
    {
        $data = Timezones::getTimezoneData();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('US', $data);
        $this->assertContains('America/New_York', $data['US']);
    }

    /**
     * @covers Timezones::getTimezoneData
     */
    public function testGetTimezoneDataWithExtendedTrue()
    {
        $data = Timezones::getTimezoneData(true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    /**
     * @covers Timezones::createLocalizedName
     */
    public function testCreateLocalizedNameWithValidData()
    {
        $reflection = new \ReflectionClass(Timezones::class);
        $method = $reflection->getMethod('createLocalizedName');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'Europe/Berlin', 'en', '%country (%offset)', 'DE');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Germany', $result); // Assuming "Germany" is returned in English
    }

    /**
     * @covers Timezones::convertTime
     */
    public function testConvertTimeWithValidTimezones()
    {
        $result = Timezones::convertTime('2024-12-14 15:00:00', 'America/New_York', 'Europe/Berlin');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    /**
     * @covers Timezones::convertTime
     */
    public function testConvertTimeWithInvalidTimezones()
    {
        $result = Timezones::convertTime('2024-12-14 15:00:00', 'Invalid/Timezone', 'Europe/Berlin');

        $this->assertNull($result);
    }

    /**
     * @covers Timezones::getCurrentTime
     */
    public function testGetCurrentTimeWithValidTimezone()
    {
        $result = Timezones::getCurrentTime('Europe/Berlin');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    /**
     * @covers Timezones::getCurrentTime
     */
    public function testGetCurrentTimeWithInvalidTimezone()
    {
        $result = Timezones::getCurrentTime('Invalid/Timezone');

        $this->assertNull($result);
    }

    /**
     * @covers Timezones::getTimezonesByCountry
     */
    public function testGetTimezonesByCountryWithValidCountry()
    {
        $result = Timezones::getTimezonesByCountry('US');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertContains('America/New_York', $result);
    }

    /**
     * @covers Timezones::getTimezonesByCountry
     */
    public function testGetTimezonesByCountryWithInvalidCountry()
    {
        $result = Timezones::getTimezonesByCountry('ZZ');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
