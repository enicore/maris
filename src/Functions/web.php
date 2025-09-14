<?php
use Enicore\Maris\Classes\Web;

/**
 * Returns a versioned asset URL with ?v= appended for cache-busting.
 */
if (!function_exists('w_url')) {
    function w_url(string $path): string {
        return Web::versionedUrl($path);
    }
}

/**
 * Returns a <script> tag with a versioned src attribute.
 */
if (!function_exists('w_script')) {
    function w_script(string $path, array $attr = []): string {
        return Web::scriptTag($path, $attr);
    }
}

/**
 * Returns a <link rel="stylesheet"> tag with a versioned href attribute.
 */
if (!function_exists('w_style')) {
    function w_style(string $path, array $attr = []): string {
        return Web::styleTag($path, $attr);
    }
}

/**
 * Returns a <link rel="icon"> tag with a versioned href attribute.
 */
if (!function_exists('w_icon')) {
    function w_icon(string $path, array $attr = []): string {
        return Web::iconTag($path, $attr);
    }
}

/**
 * Returns an <img> tag with a versioned src attribute.
 */
if (!function_exists('w_image')) {
    function w_image(string $path, array $attr = []): string {
        return Web::imageTag($path, $attr);
    }
}
