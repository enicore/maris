<?php
namespace Tests\Classes;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Utils\Code;

/**
 * @coversDefaultClass \Enicore\Maris\Utils\Code
 */
class CodeTest extends TestCase
{
    private string $encryptionKey;

    protected function setUp(): void
    {
        $this->encryptionKey = 'test-encryption-key';
    }

    /**
     * @covers ::encodeId
     * @covers ::decodeId
     */
    public function testEncodeDecodeId(): void
    {
        $id = 12345;
        $encoded = Code::encodeId($id, false);
        $this->assertNotNull($encoded);
        $this->assertSame($id, Code::decodeId($encoded));

        $randomizedEncoded = Code::encodeId($id, true);
        $this->assertNotNull($randomizedEncoded);
        $this->assertSame($id, Code::decodeId($randomizedEncoded));
    }

    /**
     * @covers ::encodeId
     * @covers ::decodeId
     */
    public function testEncodeDecodeIdWithSalt(): void
    {
        $id = 54321;
        $encoded = Code::encodeId($id, true); // Randomized salt
        $this->assertNotNull($encoded);
        $this->assertSame($id, Code::decodeId($encoded));
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptBoolean(): void
    {
        $data = true;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);

        $data = false;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptInteger(): void
    {
        $data = 12345;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptDouble(): void
    {
        $data = 12345.67;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptString(): void
    {
        $data = "This is a test string.";
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptArray(): void
    {
        $data = ['key' => 'value', 'numbers' => [1, 2, 3]];
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptObject(): void
    {
        $data = (object)['key' => 'value', 'numbers' => [1, 2, 3]];
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertEquals($data, $decrypted);
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function testEncryptDecryptNull(): void
    {
        $data = null;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertNull($decrypted);
    }

    /**
     * @covers ::encryptString
     * @covers ::decryptString
     */
    public function testEncryptDecryptStringDirect(): void
    {
        $string = "This is a test string.";
        $encrypted = Code::encryptString($string, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decryptString($encrypted, $this->encryptionKey);
        $this->assertSame($string, $decrypted);
    }

    /**
     * @covers ::generatePassword
     */
    public function testGeneratePassword(): void
    {
        $password = Code::generatePassword(16);
        $this->assertIsString($password);
        $this->assertSame(16, strlen($password));
    }

    /**
     * @covers ::generateRandomToken
     */
    public function testGenerateRandomToken(): void
    {
        $token = Code::generateRandomToken(32);
        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));
    }

    /**
     * @covers ::baseEncode
     * @covers ::baseDecode
     */
    public function testBaseEncodeDecode(): void
    {
        $number = 123456789;
        $encoded = Code::baseEncode($number, Code::BASE_62_CHARSET);
        $this->assertNotEmpty($encoded);

        $decoded = Code::baseDecode($encoded, Code::BASE_62_CHARSET);
        $this->assertSame($number, $decoded);
    }

    /**
     * @covers ::textToBinary
     * @covers ::binaryToText
     */
    public function testTextToBinaryAndBinaryToText(): void
    {
        $text = "This is a test binary string.";
        $binary = Code::binaryToText($text);
        $decodedText = Code::textToBinary($binary);

        $this->assertSame($text, $decodedText);
    }
}
