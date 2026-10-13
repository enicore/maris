<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Maris\Classes\Code;

#[CoversClass(\Enicore\Maris\Classes\Code::class)]
class CodeTest extends TestCase
{
    private string $encryptionKey;

    protected function setUp(): void
    {
        $this->encryptionKey = 'test-encryption-key';
    }

    public function testEncodeDecodeId(): void
    {
        $id = 12345;
        $encoded = Code::encodeId($id, false);
        $this->assertNotNull($encoded);
        $this->assertSame($id, Code::decodeId($encoded));

        $randomizedEncoded = Code::encodeId($id, true);
        $this->assertNotNull($randomizedEncoded);
        $this->assertSame($id, Code::decodeId($randomizedEncoded));

        // invalid input from requests
        $this->assertNull(Code::decodeId(''));
        $this->assertNull(Code::decodeId('invalid'));
        $this->assertNull(Code::decodeId('x'));
        $this->assertNull(Code::encodeId(0));
    }

    public function testEncodeDecodeIdWithSalt(): void
    {
        $id = 54321;
        $encoded = Code::encodeId($id, true); // Randomized salt
        $this->assertNotNull($encoded);
        $this->assertSame($id, Code::decodeId($encoded));
    }

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

    public function testEncryptDecryptInteger(): void
    {
        $data = 12345;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    public function testEncryptDecryptDouble(): void
    {
        $data = 12345.67;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    public function testEncryptDecryptString(): void
    {
        $data = "This is a test string.";
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    public function testEncryptDecryptArray(): void
    {
        $data = ['key' => 'value', 'numbers' => [1, 2, 3]];
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertSame($data, $decrypted);
    }

    public function testEncryptDecryptObject(): void
    {
        $data = (object)['key' => 'value', 'numbers' => [1, 2, 3]];
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptDecryptNull(): void
    {
        $data = null;
        $encrypted = Code::encrypt($data, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decrypt($encrypted, $this->encryptionKey);
        $this->assertNull($decrypted);
    }

    public function testEncryptDecryptStringDirect(): void
    {
        $string = "This is a test string.";
        $encrypted = Code::encryptString($string, $this->encryptionKey);
        $this->assertNotFalse($encrypted);

        $decrypted = Code::decryptString($encrypted, $this->encryptionKey);
        $this->assertSame($string, $decrypted);
    }

    public function testGeneratePassword(): void
    {
        $password = Code::generatePassword(16);
        $this->assertIsString($password);
        $this->assertSame(16, strlen($password));
    }

    public function testGenerateRandomToken(): void
    {
        $token = Code::generateRandomToken(32);
        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));
    }

    public function testBaseEncodeDecode(): void
    {
        $number = 123456789;
        $encoded = Code::baseEncode($number, Code::BASE_62_CHARSET);
        $this->assertNotEmpty($encoded);

        $decoded = Code::baseDecode($encoded, Code::BASE_62_CHARSET);
        $this->assertSame($number, $decoded);
    }

    public function testTextToBinaryAndBinaryToText(): void
    {
        $text = "This is a test binary string.";
        $binary = Code::binaryToText($text);
        $decodedText = Code::textToBinary($binary);

        $this->assertSame($text, $decodedText);
    }
}
