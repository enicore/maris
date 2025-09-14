<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use enshrined\svgSanitize\Sanitizer;
use Exception;

/**
 *
 * @package Enicore\Maris
 */
class Disk
{
    /**
     * Returns a string containing a relative path for saving files based on the specified id. This is used for limiting
     * the amount of files stored in a single directory.
     *
     * @param string|int $id The ID to base the directory structure on.
     * @param int $idsPerDir The number of files allowed per directory (default is 500).
     * @param int $levels The number of levels of subdirectories (default is 2).
     * @return string The relative directory path.
     */
    public static function getStructuredDirectory(string|int $id, int $idsPerDir = 500, int $levels = 2): string
    {
        if ($idsPerDir <= 0) {
            $idsPerDir = 100;
        }

        if ($levels < 1 || $levels > 3) {
            $levels = 2;
        }

        $level1 = floor($id / $idsPerDir);
        $level2 = floor($level1 / 1000);
        $level3 = floor($level2 / 1000);

        return ($levels > 2 ? sprintf("%03d", $level3 % 1000) . "/" : "") .
            ($levels > 1 ? sprintf("%03d", $level2 % 1000) . "/" : "") .
            sprintf("%03d", $level1 % 1000) . "/";
    }

    /**
     * Ensures the provided string is a valid file name by removing or replacing invalid characters.
     *
     * @param string $string The string to sanitize into a valid file name.
     * @return string The sanitized file name.
     */
    public static function ensureFileName(string $string): string
    {
        $result = preg_replace(["/[\/\\\:?*+%|\"<>]/i", "/_{2,}/"], "_", strtolower($string));
        return trim($result, "_ \t\n\r\0\x0B") ?: "unknown";
    }

    /**
     * Returns a unique file name. This function generates a random name, then checks if the file with this name already
     * exists in the specified directory. If it does, it generates a new random file name.
     *
     * @param string $path The directory path where the file should be stored.
     * @param bool|string $ext The file extension (optional).
     * @param bool|string $prefix A prefix to prepend to the file name (optional).
     * @return string A unique file name.
     */

    public static function getUniqueFileName(string $path, bool|string $ext = false, bool|string $prefix = false): string
    {
        if (strlen($ext) && $ext[0] != ".") {
            $ext = "." . $ext;
        }

        $path = rtrim($path, '/');

        do {
            $fileName = uniqid($prefix, true) . $ext;
        } while (file_exists($path . '/' . $fileName));

        return $fileName;
    }

    /**
     * Extracts the file extension from the given file name.
     *
     * @param string $fileName The file name to extract the extension from.
     * @return string The file extension in lowercase.
     */
    public static function getExtension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Creates an empty directory with write permissions. It returns true if the directory already exists and is
     * writable. Also, if umask is set, mkdir won't create the directory with 0777 permissions, for example, if umask is
     * 0022, the outcome will be 0777-0022 = 0755, so we reset umask before creating the directory.
     *
     * @param string $dir The directory path to create.
     * @return bool True if the directory was created or already exists and is writable; false otherwise.
     */

    public static function makeDir(string $dir): bool
    {
        if (file_exists($dir)) {
            return is_writable($dir);
        }

        $umask = umask(0);
        $result = @mkdir($dir, 0777, true);
        umask($umask);

        return $result;
    }

    /**
     * Recursively removes a directory and its contents. Optionally, it can remove only the contents or follow symbolic
     * links.
     *
     * @param string $dir The directory path to remove.
     * @param bool $followLinks Whether to follow symbolic links.
     * @param bool $contentsOnly If true, only the contents of the directory are removed.
     * @return bool True if the operation was successful; false otherwise.
     */
    public static function removeDir(string $dir, bool $followLinks = false, bool $contentsOnly = false): bool
    {
        if (empty($dir) || !is_dir($dir)) {
            return true;
        }

        $dir = Text::addSlash($dir);
        $files = array_diff(scandir($dir), [".", ".."]);

        foreach ($files as $file) {
            if (is_dir($dir . $file)) {
                self::removeDir($dir . $file, $followLinks);
                continue;
            }

            if (is_link($dir . $file) && $followLinks) {
                unlink(readlink($dir . $file));
            }

            unlink($dir . $file);
        }

        return $contentsOnly || rmdir($dir);
    }

    /**
     * Deletes files in the specified directory that are older than the given time interval.
     *
     * @param string $directory The directory to scan for outdated files.
     * @param string|int $interval The time interval (in seconds) that defines when files should be considered outdated.
     * @param string &$error Error message, if any.
     * @return bool Returns true if files are deleted successfully, false otherwise.
     */
    public static function deleteOutdatedFiles(string $directory, string|int $interval, string &$error): bool
    {
        // remove trailing slash
        $directory = rtrim($directory, '/\\');

        if (!$handle = opendir($directory)) {
            $error = "Cannot open directory"; // not translated, logged for admins only
            return false;
        }

        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != ".." && filemtime($directory . '/' . $file) < time() - $interval) {
                unlink($directory . '/' . $file);
            }
        }

        closedir($handle);
        return true;
    }

    /**
     * Returns the maximum file upload size allowed by PHP based on `upload_max_filesize` and `post_max_size` settings.
     * Returns the smaller value between the two. The function returns 0 if no upload limit is specified. To represent
     * this value in a user-readable format, use sizeToString().
     *
     * @return float|int Returns the maximum file size in bytes, or 0 if no limit is set.
     */
    public static function getMaxFileUploadSize(): float|int
    {
        $bytes = 0;

        if (($b = self::sizeToBytes(ini_get('post_max_size'))) > 0) {
            $bytes = $b;
        }

        if (($b = self::sizeToBytes(ini_get('upload_max_filesize'))) > 0 && $b < $bytes) {
            $bytes = $b;
        }

        return $bytes;
    }

    /**
     * Converts PHP-type size string to bytes. For example 64M will become 67108864. This is useful for converting
     * the php.ini values to bytes. The function is courtesy of Drupal.
     *
     * @param string $size
     * @return int
     */
    public static function sizeToBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9.]/', '', $size); // Remove the non-numeric characters from the size.

        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    /**
     * Retrieves the MIME type of the file based on its content. Special handling is done for SVG and ICO file types.
     *
     * @param string $filePath The path to the file whose MIME type is to be determined.
     * @return string The MIME type of the file, simplified for SVG and ICO files.
     */
    public static function getFileMimeType(string $filePath): string
    {
        $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);

        // it could be "image/svg+xml" so we're simplifying
        if (str_starts_with($type, "image/svg")) {
            return "image/svg";
        }

        // icon could also be this
        if ($type == "image/vnd.microsoft.icon") {
            return "image/x-icon";
        }

        return $type;
    }

    /**
     * Moves the uploaded image file, checking and re-saving it to avoid any potential security risks.
     *
     * @param array $uploadInfo - The uploaded file's information (from `$_FILES['file']`).
     * @param string $targetFile - The target path where the image should be saved.
     * @param bool|int $maxSize - The maximum allowed file size in bytes (default is no limit).
     * @param null|string $error - Holds any error message if an error occurs.
     * @param bool|int $maxWidth - The maximum width allowed for the image (default is no limit).
     * @param bool|int $maxHeight - The maximum height allowed for the image (default is no limit).
     * @param bool|string $forceTargetType - Specifies the target file type (e.g., 'image/jpeg', 'image/png').
     * @param bool $bypassUploadedCheck - if true, skips is_uploaded_file() check for unit tests or unique scenarios
     * @return string|bool Returns the path of the saved file on success, or false on failure.
     */
    public static function saveUploadedImage(array $uploadInfo, string $targetFile, bool|int $maxSize = false,
                                             null|string &$error = null, bool|int $maxWidth = false,
                                             bool|int $maxHeight = false,
                                             bool|string $forceTargetType = false,
                                             bool $bypassUploadedCheck = false): string|bool
    {
        $allowedExtensions = ["png", "jpg", "jpeg", "gif", "svg", "ico"];
        $allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/svg", "image/svg+xml", "image/x-icon"];
        $filePath = $uploadInfo['tmp_name'];
        $fileName = self::ensureFileName($uploadInfo['name']);
        $fileSize = $uploadInfo['size'];
        $image = null;
        $type = "";

        try {
            // check if the file name is set
            if (empty($fileName) || $fileName == "unknown") {
                throw new Exception("Invalid file name. (44350)");
            }

            // check if file too large
            if ($maxSize && $fileSize > $maxSize) {
                throw new Exception(sprintf("The file size exceeds the allowed %s.", Text::sizeToString($maxSize)));
            }

            // check if there is an upload error
            if (!empty($uploadInfo['error'])) {
                throw new Exception("The file has not been uploaded properly. (44351)");
            }

            // check if the uploaded file exists
            if (empty($filePath) || empty($fileSize) || !file_exists($filePath)) {
                throw new Exception("The file has not been uploaded properly. (44352)");
            }

            // check if the file is an uploaded file (can bypass for unit tests or unique scenarios)
            if (!$bypassUploadedCheck && !is_uploaded_file($filePath)) {
                throw new Exception("The file has not been uploaded properly. (44353)");
            }

            // check the uploaded file extension
            $pathInfo = pathinfo($fileName);

            if (!in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {
                throw new Exception("Invalid file extension. The allowed extensions are: jpg, png, gif, svg.");
            }

            // check if dstFile has an allowed extension (allow only no extension, svg, png, jpg and gif)
            $pathInfo = pathinfo($targetFile);

            if (!empty($pathInfo['extension']) && !in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {
                throw new Exception("Invalid target extension. (44354)");
            }

            // check if target dir exists and try creating it if it doesn't
            if (!self::makeDir(dirname($targetFile))) {
                throw new Exception("Cannot create target directory or the directory is not writable. (35112)");
            }

            // delete the target file is if exists
            if (file_exists($targetFile) && !@unlink($targetFile)) {
                throw new Exception("Cannot overwrite the target file. (44356).");
            }

            // get the image mime type
            $type = self::getFileMimeType($filePath);

            // open the image
            switch ($type) {
                case "image/jpeg":
                    $image = imagecreatefromjpeg($filePath);
                    break;

                case "image/png":
                    $image = imagecreatefrompng($filePath);
                    break;

                case "image/gif":
                    $image = imagecreatefromgif($filePath);
                    break;

                case "image/svg":
                    $sanitizer = new Sanitizer();
                    $image = $sanitizer->sanitize(file_get_contents($filePath));
                    break;

                case "image/x-icon":
                    $image = "image";
                    break;

                default:
                    $image = false;
            }

            if (!$image) {
                throw new Exception("This file is not a valid image.");
            }

            // if new width and height are specified, resize the image
            if ($maxWidth && $maxHeight && $type != "image/svg" && $type != "image/x-icon") {
                // get the original image size
                $width = imagesx($image);
                $height = imagesy($image);

                // resize only if current dimensions are larger than the new dimensions
                if ($width > $maxWidth || $height > $maxHeight) {
                    // adjust new height or width to retain the aspect ratio
                    if ($width / $maxWidth > $height / $maxHeight) {
                        $maxHeight = (int)round($maxWidth / ($width / $height));
                    } else {
                        $maxWidth = (int)round($maxHeight / ($height / $width));
                    }

                    $newImage = imagecreatetruecolor($maxWidth, $maxHeight);

                    // preserve transparency (gif is more complicated, don't worry about it for now)
                    if ($type == "image/png") {
                        imagealphablending($newImage, false);
                        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                        imagefilledrectangle($newImage, 0, 0, $maxWidth, $maxHeight, $transparent);
                    }

                    // copy the image content
                    if (!imagecopyresampled($newImage, $image, 0, 0, 0, 0, $maxWidth, $maxHeight, $width, $height)) {
                        throw new Exception("Cannot resample image.");
                    }

                    // destroy the original image, and assign new image to old var so the saving can continue below
                    imagedestroy($image);
                    $image = $newImage;
                }
            }

            // save the image to the target file
            if ($forceTargetType && $type != "image/svg") {
                $type = $forceTargetType;
            }

            $targetFile = preg_replace('/\.(jpeg|jpg|png|gif|svg|ico)$/i', '', $targetFile);

            switch ($type) {
                case "image/jpeg":
                    $targetFile .= ".jpg";
                    $result = imagejpeg($image, $targetFile, 75);
                    break;

                case "image/png":
                    $targetFile .= ".png";
                    imagesavealpha($image , true); // preserve png transparency
                    $result = imagepng($image, $targetFile, 9);
                    break;

                case "image/gif":
                    $targetFile .= ".gif";
                    $result = imagegif($image, $targetFile);
                    break;

                case "image/svg":
                    $targetFile .= ".svg";
                    $result = file_put_contents($targetFile, $image);
                    break;

                case "image/x-icon":
                    $targetFile .= ".ico";
                    $result = copy($filePath, $targetFile);
                    break;

                default:
                    $result = false;
            }

            // verify if the image was successfully saved
            if (!$result || !file_exists($targetFile)) {
                throw new Exception("Cannot save the uploaded image (44356).");
            }

            // verify the target file mime type
            if (!in_array($newType = self::getFileMimeType($targetFile), $allowedTypes)) {
                unlink($targetFile);
                throw new Exception("Cannot save the uploaded image (44357). [$newType]");
            }

            // remove the source file and image resource
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            if ($type != "image/svg" && $type != "image/x-icon") {
                imagedestroy($image);
            }

            return $targetFile;

        } catch (Exception $e) {
            if ($image && $type != "image/svg" && $type != "image/x-icon") {
                imagedestroy($image);
            }

            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            $error = $e->getMessage();
            return false;
        }
    }
}
