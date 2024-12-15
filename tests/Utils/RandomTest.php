<?php
namespace Tests\Utils;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Utils\Random;

/**
 * @coversDefaultClass \Enicore\Maris\Utils\Random
 */
class RandomTest extends TestCase
{
    /**
     * @covers ::ip
     */
    public function testIp(): void
    {
        $ip = Random::ip();
        $this->assertMatchesRegularExpression('/^(\d{1,3}\.){3}\d{1,3}$/', $ip);

        foreach (explode('.', $ip) as $segment) {
            $this->assertGreaterThanOrEqual(1, (int)$segment);
            $this->assertLessThanOrEqual(255, (int)$segment);
        }
    }

    /**
     * @covers ::uuid
     */
    public function testUuid(): void
    {
        $uuid = Random::uuid();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    /**
     * @covers ::tld
     */
    public function testTld(): void
    {
        $tld = Random::tld();
        $this->assertIsString($tld);
        $this->assertNotEmpty($tld);
    }

    /**
     * @covers ::domain
     */
    public function testDomain(): void
    {
        $domain = Random::domain();
        $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z]+)*\.[a-z]{2,}$/', $domain);
    }

    /**
     * @covers ::url
     */
    public function testUrl(): void
    {
        $urlWithPath = Random::url();
        $this->assertMatchesRegularExpression('/^https:\/\/[a-z]+(\.[a-z]+)*\.[a-z]{2,}(\/[a-z]+)*$/', $urlWithPath);

        $urlWithoutPath = Random::url(false);
        $this->assertMatchesRegularExpression('/^https:\/\/[a-z]+(\.[a-z]+)*\.[a-z]{2,}$/', $urlWithoutPath);
    }

    /**
     * @covers ::number
     */
    public function testNumber(): void
    {
        $number = Random::number(5, 10);
        $this->assertIsString($number);
        $this->assertGreaterThanOrEqual(5, strlen($number));
        $this->assertLessThanOrEqual(10, strlen($number));
    }

    /**
     * @covers ::string
     */
    public function testString(): void
    {
        $string = Random::string(5, 10, true, true);
        $this->assertIsString($string);
        $this->assertGreaterThanOrEqual(5, strlen($string));
        $this->assertLessThanOrEqual(10, strlen($string));
    }

    /**
     * @covers ::color
     */
    public function testColor(): void
    {
        $color = Random::color();
        $this->assertIsString($color);
        $this->assertNotEmpty($color);
    }

    /**
     * @covers ::name
     */
    public function testName(): void
    {
        $name = Random::name(2, 3);
        $this->assertMatchesRegularExpression('/^([A-Z][a-z]+ ){1,2}[A-Z][a-z]+$/', $name);
    }

    /**
     * @covers ::phoneNumber
     */
    public function testPhoneNumber(): void
    {
        $phoneNumber = Random::phoneNumber();
        $this->assertMatchesRegularExpression('/^\(\d{3}\) \d{3}-\d{4}$/', $phoneNumber);
    }

    /**
     * @covers ::email
     */
    public function testEmail(): void
    {
        $email = Random::email();
        $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z]+)?@[a-z]+\.[a-z]{2,}$/', $email);
    }

    /**
     * @covers ::countryCode
     */
    public function testCountryCode(): void
    {
        $countryCode = Random::countryCode();
        $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $countryCode);
    }

    /**
     * @covers ::word
     */
    public function testWord(): void
    {
        $word = Random::word();
        $this->assertIsString($word);
        $this->assertNotEmpty($word);
    }
}
