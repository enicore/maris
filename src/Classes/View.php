<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use Psr\Http\Message\ResponseInterface as Response;

class View
{
    public function __construct(protected string $viewPath = '')
    {
        if (empty($viewPath)) {
            $this->viewPath = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../src/views');
            if (!is_dir($this->viewPath)) {
                error_log("View: viewPath doesn't exist.");
            }
        }
        $this->viewPath = rtrim($this->viewPath, '/') . '/';
    }

    /**
     * Renders a view file into HTML with provided data.
     *
     * @param string $view The view name (without .php).
     * @param array $data Variables to extract into the view.
     * @return string The rendered HTML output.
     */
    public function getViewHtml(string $view, array $data = []): string
    {
        if (!$view) $view = 'home';
        $view = ltrim(str_replace(['..', '\\'], ['', '/'], $view), '/');

        if (!$this->viewExists($view)) {
            $view = 'error';
            $data = ['code' => 404, 'message' => 'Page not found (338119)'];
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $this->viewPath . ltrim("$view.php", '/');
        return (string)ob_get_clean();
    }

    /**
     * Renders the layout with embedded view content and CSRF token.
     *
     * @param string $view The view name (without .php).
     * @param array $data Variables to extract into the layout and view.
     * @return string The rendered HTML output including layout.
     */
    public function getLayoutHtml(string $view, array $data = []): string
    {
        $data['csrf'] = Session::getCsrfToken();
        $data['content'] = $this->getViewHtml($view, $data);

        extract($data, EXTR_SKIP);
        ob_start();
        include $this->viewPath . 'layout.php';
        return (string)ob_get_clean();
    }

    /**
     * Checks if a view file exists.
     *
     * @param string $view The view name (without .php).
     * @return bool True if the view file exists, false otherwise.
     */
    public function viewExists(string $view): bool
    {
        return file_exists($this->viewPath . ltrim("$view.php", '/'));
    }
}
