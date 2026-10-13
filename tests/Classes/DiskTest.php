<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Maris\Classes\Disk;

#[CoversClass(\Enicore\Maris\Classes\Disk::class)]
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

    public function testGetStructuredDirectory(): void
    {
        $id = 12345;
        $path = Disk::getStructuredDirectory($id, 500, 2);
        $this->assertSame('000/024/', $path);

        $path = Disk::getStructuredDirectory($id, 100, 3);
        $this->assertSame('000/000/123/', $path);
    }

    public function testEnsureFileName(): void
    {
        $input = 'invalid:/\file*name.txt';
        $output = Disk::ensureFileName($input);
        $this->assertSame('invalid_file_name.txt', $output);

        $input = '';
        $output = Disk::ensureFileName($input);
        $this->assertSame('unknown', $output);
    }

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

    public function testMakeDir(): void
    {
        $newDir = $this->tempDir . '/new_directory';
        $result = Disk::makeDir($newDir);
        $this->assertTrue($result);
        $this->assertDirectoryExists($newDir);

        // Cleanup
        rmdir($newDir);
    }

    public function testRemoveDir(): void
    {
        $dir = $this->tempDir . '/directory_to_remove';
        mkdir($dir);
        touch($dir . '/file.txt');

        $result = Disk::removeDir($dir);
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testDeleteOutdatedFiles(): void
    {
        $file = $this->tempDir . '/old_file.txt';
        touch($file, time() - 3600); // File is 1 hour old

        $error = '';
        $result = Disk::deleteOutdatedFiles($this->tempDir, 1800, $error); // Delete files older than 30 minutes
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($file);
    }

    public function testGetMaxFileUploadSize(): void
    {
        $size = Disk::getMaxFileUploadSize();
        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    public function testGetFileMimeType(): void
    {
        $file = $this->tempDir . '/test.txt';
        file_put_contents($file, 'This is a test file.');
        $mimeType = Disk::getFileMimeType($file);
        $this->assertSame('text/plain', $mimeType);

        // Cleanup
        unlink($file);
    }

    public function testSaveUploadedImage(): void
    {
        $sourceFile = $this->tempDir . '/source.jpg';
        $targetFile = $this->tempDir . '/target.jpg';

        // Create a mock uploaded file
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $sourceFile);

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

    public function testGetExtension(): void
    {
        $this->assertSame('jpg', Disk::getExtension('/path/photo.JPG'));
        $this->assertSame('gz', Disk::getExtension('archive.tar.gz'));
        $this->assertSame('', Disk::getExtension('README'));
    }

    public function testSizeToBytes(): void
    {
        $this->assertSame(67108864, Disk::sizeToBytes('64M'));
        $this->assertSame(1536, Disk::sizeToBytes('1.5k'));
        $this->assertSame(2147483648, Disk::sizeToBytes('2G'));
        $this->assertSame(1000, Disk::sizeToBytes('1000'));
        $this->assertSame(0, Disk::sizeToBytes('0'));
    }

    public function testGetMaxFileUploadSizeLimits(): void
    {
        // the settings can't be changed at runtime: read them in a new process
        $get = function (string $post, string $upload): int {
            $autoload = escapeshellarg(__DIR__ . '/../../vendor/autoload.php');
            $code = escapeshellarg("require $autoload; echo Enicore\\Maris\\Classes\\Disk::getMaxFileUploadSize();");
            return (int)shell_exec(PHP_BINARY . " -d post_max_size=$post -d upload_max_filesize=$upload -r $code");
        };

        $this->assertSame(2097152, $get('8M', '2M'));
        $this->assertSame(1048576, $get('1M', '2M'));
        // 0 is no limit
        $this->assertSame(2097152, $get('0', '2M'));
        $this->assertSame(8388608, $get('8M', '0'));
        $this->assertSame(0, $get('0', '0'));
    }

    public function testMakeDirNested(): void
    {
        $dir = $this->tempDir . '/a/b/c';
        $this->assertTrue(Disk::makeDir($dir));
        $this->assertDirectoryExists($dir);
        // an existing directory is fine
        $this->assertTrue(Disk::makeDir($dir));
    }

    public function testRemoveDirNested(): void
    {
        $dir = $this->tempDir . '/remove';
        mkdir($dir . '/sub/subsub', 0777, true);
        touch($dir . '/file.txt');
        touch($dir . '/sub/subsub/file.txt');

        $this->assertTrue(Disk::removeDir($dir, contentsOnly: true));
        $this->assertDirectoryExists($dir);
        $this->assertSame(['.', '..'], scandir($dir));

        $this->assertTrue(Disk::removeDir($dir . '/'));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertTrue(Disk::removeDir($dir));
        $this->assertTrue(Disk::removeDir(''));
    }

    public function testRemoveDirLinks(): void
    {
        $dir = $this->tempDir . '/remove';
        $targetDir = $this->tempDir . '/target';
        mkdir($dir);
        mkdir($targetDir);
        touch($targetDir . '/file.txt');
        touch($this->tempDir . '/file.txt');
        symlink($targetDir, $dir . '/dirLink');
        symlink($this->tempDir . '/file.txt', $dir . '/fileLink');

        // the links are removed, not their targets
        $this->assertTrue(Disk::removeDir($dir));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertFileExists($targetDir . '/file.txt');
        $this->assertFileExists($this->tempDir . '/file.txt');

        // following the links removes the targets too
        mkdir($dir);
        symlink($targetDir, $dir . '/dirLink');
        symlink($this->tempDir . '/file.txt', $dir . '/fileLink');
        $this->assertTrue(Disk::removeDir($dir, true));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertDirectoryDoesNotExist($targetDir);
        $this->assertFileDoesNotExist($this->tempDir . '/file.txt');
    }

    public function testDeleteOutdatedFilesKeepsNewFiles(): void
    {
        touch($this->tempDir . '/old.txt', time() - 3600);
        touch($this->tempDir . '/new.txt');

        $error = '';
        $this->assertTrue(Disk::deleteOutdatedFiles($this->tempDir . '/', '1800', $error));
        $this->assertFileDoesNotExist($this->tempDir . '/old.txt');
        $this->assertFileExists($this->tempDir . '/new.txt');

        $this->assertFalse(Disk::deleteOutdatedFiles($this->tempDir . '/missing', 1800, $error));
        $this->assertSame('Cannot open directory', $error);
    }

    public function testGetFileMimeTypeImages(): void
    {
        file_put_contents($this->tempDir . '/image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertSame('image/svg', Disk::getFileMimeType($this->tempDir . '/image.svg'));
        $this->assertSame('image/x-icon', Disk::getFileMimeType(__DIR__ . '/../Fixtures/images/icon.ico'));
    }

    /**
     * Saves an uploaded file with saveUploadedImage() and returns the result.
     */
    private function save(string $name, string $content, string $targetFile, ?string &$error = null,
                          array $options = []): string|bool
    {
        $sourceFile = $this->tempDir . '/upload_' . uniqid();
        file_put_contents($sourceFile, $content);
        $uploadInfo = ['tmp_name' => $sourceFile, 'name' => $name, 'size' => strlen($content), 'error' => 0];

        return Disk::saveUploadedImage($uploadInfo, $targetFile, $options['maxSize'] ?? false, $error,
            $options['maxWidth'] ?? false, $options['maxHeight'] ?? false, $options['type'] ?? false,
            $options['bypass'] ?? true);
    }

    private function image(int $width, int $height, string $type): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        match ($type) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'jpg' => imagejpeg($image),
        };
        return ob_get_clean();
    }

    public function testSaveUploadedImageTypes(): void
    {
        // the target extension follows the image type, whatever the target name has
        $result = $this->save('a.png', $this->image(10, 10, 'png'), $this->tempDir . '/out/a.jpg');
        $this->assertSame($this->tempDir . '/out/a.png', $result);
        $this->assertSame('image/png', Disk::getFileMimeType($result));

        $result = $this->save('a.gif', $this->image(10, 10, 'gif'), $this->tempDir . '/b');
        $this->assertSame($this->tempDir . '/b.gif', $result);
        $this->assertSame('image/gif', Disk::getFileMimeType($result));

        $icon = file_get_contents(__DIR__ . '/../Fixtures/images/icon.ico');
        $result = $this->save('a.ico', $icon, $this->tempDir . '/c.ico');
        $this->assertSame($this->tempDir . '/c.ico', $result);
        $this->assertSame($icon, file_get_contents($result));

        // the uploaded file is removed
        $this->assertSame([], glob($this->tempDir . '/upload_*'));
    }

    public function testSaveUploadedImageForceType(): void
    {
        $result = $this->save('a.png', $this->image(10, 10, 'png'), $this->tempDir . '/a',
            options: ['type' => 'image/jpeg']);
        $this->assertSame($this->tempDir . '/a.jpg', $result);
        $this->assertSame('image/jpeg', Disk::getFileMimeType($result));

        // icons and svgs are saved as they are
        $icon = file_get_contents(__DIR__ . '/../Fixtures/images/icon.ico');
        $result = $this->save('a.ico', $icon, $this->tempDir . '/b', options: ['type' => 'image/png']);
        $this->assertSame($this->tempDir . '/b.ico', $result);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
        $result = $this->save('a.svg', $svg, $this->tempDir . '/c', options: ['type' => 'image/png']);
        $this->assertSame($this->tempDir . '/c.svg', $result);
    }

    public function testSaveUploadedImageResize(): void
    {
        // the aspect ratio is kept
        $result = $this->save('a.png', $this->image(200, 100, 'png'), $this->tempDir . '/a',
            options: ['maxWidth' => 50, 'maxHeight' => 50]);
        $this->assertSame([50, 25], array_slice(getimagesize($result), 0, 2));

        $result = $this->save('a.jpg', $this->image(100, 200, 'jpg'), $this->tempDir . '/b',
            options: ['maxWidth' => 50, 'maxHeight' => 50]);
        $this->assertSame([25, 50], array_slice(getimagesize($result), 0, 2));

        // smaller images aren't enlarged
        $result = $this->save('a.gif', $this->image(20, 10, 'gif'), $this->tempDir . '/c',
            options: ['maxWidth' => 50, 'maxHeight' => 50]);
        $this->assertSame([20, 10], array_slice(getimagesize($result), 0, 2));
    }

    public function testSaveUploadedImageSanitizesSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' .
            '<script>alert(1)</script>' .
            '<rect width="10" height="10" onclick="alert(2)" fill="url(#local)"/>' .
            '<image xlink:href="https://tracker.example.com/pixel.png" width="1" height="1"/>' .
            '<rect width="1" height="1" style="fill:url(https://tracker.example.com/fill)"/>' .
            '</svg>';

        $result = $this->save('a.svg', $svg, $this->tempDir . '/a.svg');
        $saved = file_get_contents($result);

        $this->assertStringContainsString('<rect width="10" height="10" fill="url(#local)"', $saved);
        $this->assertStringNotContainsString('script', $saved);
        $this->assertStringNotContainsString('alert', $saved);
        // remote references would be loaded when the svg is displayed
        $this->assertStringNotContainsString('tracker.example.com', $saved);
    }

    public function testSaveUploadedImageErrors(): void
    {
        $png = $this->image(10, 10, 'png');
        $target = $this->tempDir . '/target.png';

        $this->assertFalse($this->save('', $png, $target, $error));
        $this->assertSame('Invalid file name. (44350)', $error);

        $this->assertFalse($this->save('a.png', $png, $target, $error, ['maxSize' => 10]));
        $this->assertStringStartsWith('The file size exceeds the allowed ', $error);

        $this->assertFalse($this->save('a.txt', $png, $target, $error));
        $this->assertStringStartsWith('Invalid file extension.', $error);

        $this->assertFalse($this->save('noextension', $png, $target, $error));
        $this->assertStringStartsWith('Invalid file extension.', $error);

        $this->assertFalse($this->save('a.png', $png, $this->tempDir . '/target.php', $error));
        $this->assertSame('Invalid target extension. (44354)', $error);

        // a file that isn't an uploaded file (is_uploaded_file())
        $this->assertFalse($this->save('a.png', $png, $target, $error, ['bypass' => false]));
        $this->assertSame('The file has not been uploaded properly. (44353)', $error);

        // content that isn't an allowed image, whatever its name
        $this->assertFalse($this->save('a.png', '<?php echo 1;', $target, $error));
        $this->assertSame('This file is not a valid image.', $error);

        $this->assertFalse($this->save('a.svg', '<svg', $target, $error));
        $this->assertSame('This file is not a valid image.', $error);

        $uploadInfo = ['tmp_name' => $this->tempDir . '/missing', 'name' => 'a.png', 'size' => 10, 'error' => 0];
        $this->assertFalse(Disk::saveUploadedImage($uploadInfo, $target, false, $error, bypassUploadedCheck: true));
        $this->assertSame('The file has not been uploaded properly. (44352)', $error);

        $uploadInfo['error'] = UPLOAD_ERR_PARTIAL;
        $this->assertFalse(Disk::saveUploadedImage($uploadInfo, $target, false, $error, bypassUploadedCheck: true));
        $this->assertSame('The file has not been uploaded properly. (44351)', $error);

        $this->assertFileDoesNotExist($target);
        // the uploaded files are removed after failures too
        $this->assertSame([], glob($this->tempDir . '/upload_*'));
    }
}
