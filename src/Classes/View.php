<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

/**
 * Renders PHP view files. A page is a view rendered inside a layout:
 * - the layout (layout.php by default) gets $content (the rendered view), $csrf (the session's CSRF token, for
 *   <meta name="csrf"> that RavenSpa sends with its requests; empty if no session is active, see
 *   SessionMiddleware), and the page's data
 * - the views get the page's data as variables
 * - error.php renders the error pages' content, with $code and $message
 *
 * @package Enicore\Maris
 */
class View
{
    /**
     * @param string $viewPath The directory of the view files; if empty, src/views next to the directory of the
     *                         running script.
     */
    public function __construct(protected string $viewPath = '')
    {
        if (empty($viewPath)) {
            $this->viewPath = (string)realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../src/views');
            if (!is_dir($this->viewPath)) {
                error_log("View: viewPath doesn't exist.");
            }
        }
        $this->viewPath = rtrim($this->viewPath, '/') . '/';
    }

    /**
     * Renders a view file into HTML with provided data. A view that doesn't exist renders the error view (404).
     *
     * @param string $view The view name (without .php), "home" if empty.
     * @param array $data Variables to extract into the view.
     * @return string The rendered HTML output.
     */
    public function getViewHtml(string $view, array $data = []): string
    {
        if (!$view) $view = 'home';
        $view = $this->cleanName($view);

        if (!$this->viewExists($view)) {
            $view = 'error';
            $data = ['code' => 404, 'message' => 'Page not found (338119)'];
        }

        return $this->render($view, $data);
    }

    /**
     * Renders the layout with the view as its content, and the CSRF token. The data is available to both.
     *
     * @param string|null $view The view name (without .php); null for the layout without content (RavenSpa loads the
     *                          content of the page itself).
     * @param array $data Variables to extract into the layout and view.
     * @param string $layout The layout's view name.
     * @return string The rendered HTML output including layout.
     */
    public function getLayoutHtml(?string $view, array $data = [], string $layout = 'layout'): string
    {
        // only with a session, so pages outside the session routes (errors for bots...) don't create sessions
        $data['csrf'] = session_status() === PHP_SESSION_ACTIVE ? Session::getCsrfToken() : '';
        $data['content'] = $view === null ? '' : $this->getViewHtml($view, $data);

        return $this->render($this->cleanName($layout), $data);
    }

    /**
     * Checks if a view file exists.
     *
     * @param string $view The view name (without .php).
     * @return bool True if the view file exists, false otherwise.
     */
    public function viewExists(string $view): bool
    {
        return is_file($this->viewPath . $this->cleanName($view) . '.php');
    }

    /**
     * Removes what could leave the view directory.
     */
    private function cleanName(string $view): string
    {
        return ltrim(str_replace(['..', '\\', "\0"], ['', '/', ''], $view), '/');
    }

    private function render(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            include $this->viewPath . "$view.php";
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string)ob_get_clean();
    }
}
