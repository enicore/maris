<?php
namespace Tests\Utils;

use PHPUnit\Framework\TestCase;
use Enicore\Maris\Utils\Disk;

/**
 * @coversDefaultClass \Enicore\Maris\Utils\Disk
 */
class DiskTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/DiskTest_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanUpDir($this->tempDir);
    }

    private function cleanUpDir(string $dir): void
    {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if ($item !== '.' && $item !== '..') {
                    $path = $dir . '/' . $item;
                    is_dir($path) ? $this->cleanUpDir($path) : unlink($path);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @covers ::getStructuredDirectory
     */
    public function testGetStructuredDirectory(): void
    {
        $id = 12345;
        $path = Disk::getStructuredDirectory($id, 500, 2);
        $this->assertSame('000/024/', $path);

        $path = Disk::getStructuredDirectory($id, 100, 3);
        $this->assertSame('000/000/123/', $path);
    }

    /**
     * @covers ::ensureFileName
     */
    public function testEnsureFileName(): void
    {
        $input = 'invalid:/\file*name.txt';
        $output = Disk::ensureFileName($input);
        $this->assertSame('invalid_file_name.txt', $output);

        $input = '';
        $output = Disk::ensureFileName($input);
        $this->assertSame('unknown', $output);
    }

    /**
     * @covers ::getUniqueFileName
     */
    public function testGetUniqueFileName(): void
    {
        $uniqueFileName = Disk::getUniqueFileName($this->tempDir, 'txt', 'test');
        $this->assertStringEndsWith('.txt', $uniqueFileName);
        $this->assertStringStartsWith('test', $uniqueFileName);

        // Cleanup file if created
        if (file_exists($this->tempDir . '/' . $uniqueFileName)) {
            unlink($this->tempDir . '/' . $uniqueFileName);
        }
    }

    /**
     * @covers ::makeDir
     */
    public function testMakeDir(): void
    {
        $newDir = $this->tempDir . '/new_directory';
        $result = Disk::makeDir($newDir);
        $this->assertTrue($result);
        $this->assertDirectoryExists($newDir);

        // Cleanup
        rmdir($newDir);
    }

    /**
     * @covers ::removeDir
     */
    public function testRemoveDir(): void
    {
        $dir = $this->tempDir . '/directory_to_remove';
        mkdir($dir);
        touch($dir . '/file.txt');

        $result = Disk::removeDir($dir);
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($dir);
    }

    /**
     * @covers ::deleteOutdatedFiles
     */
    public function testDeleteOutdatedFiles(): void
    {
        $file = $this->tempDir . '/old_file.txt';
        touch($file, time() - 3600); // File is 1 hour old

        $error = '';
        $result = Disk::deleteOutdatedFiles($this->tempDir, 1800, $error); // Delete files older than 30 minutes
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($file);
    }

    /**
     * @covers ::getMaxFileUploadSize
     */
    public function testGetMaxFileUploadSize(): void
    {
        $size = Disk::getMaxFileUploadSize();
        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    /**
     * @covers ::getFileMimeType
     */
    public function testGetFileMimeType(): void
    {
        $file = $this->tempDir . '/test.txt';
        file_put_contents($file, 'This is a test file.');
        $mimeType = Disk::getFileMimeType($file);
        $this->assertSame('text/plain', $mimeType);

        // Cleanup
        unlink($file);
    }

    /**
     * @covers ::saveUploadedImage
     */
    public function testSaveUploadedImage(): void
    {
        $sourceFile = $this->tempDir . '/source.jpg';
        $targetFile = $this->tempDir . '/target.jpg';

        // Create a mock uploaded file
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $sourceFile);
        imagedestroy($image);

        $uploadInfo = [
            'tmp_name' => $sourceFile,
            'name' => 'test.jpg',
            'size' => filesize($sourceFile),
            'error' => UPLOAD_ERR_OK
        ];

        $error = null;
        $result = Disk::saveUploadedImage($uploadInfo, $targetFile, 500000, $error, bypassUploadedCheck: true);

        $this->assertNotFalse($result);
        $this->assertFileExists($targetFile);
        $this->assertSame($targetFile, $result);

        // Cleanup
        unlink($sourceFile);
        unlink($targetFile);
    }
}
