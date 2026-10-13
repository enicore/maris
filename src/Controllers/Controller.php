<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Controllers;

use Enicore\Maris\Classes\Http;
use Enicore\Maris\Classes\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Base controller for Slim apps with RavenSpa pages and JSON actions.
 *
 * RavenSpa loads the layout with a GET request, then the page's content with a POST request to the same URL, which
 * expects {"success": true, "data": {"title": ..., "html": ..., "data": {...}}}. So an SPA routes GET requests to
 * layoutRoute() and POST requests to the page controllers, which call sendPage():
 *
 * $app->get('/{path:.*}', [Controller::class, 'layoutRoute']);
 * $app->post('/', [DashboardController::class, 'page']);
 * $app->post('/{path:.+}', [Controller::class, 'staticPageRoute']);
 *
 * sendPage() sends a GET request the whole page (the layout with the content), so it also works without RavenSpa.
 *
 * @package Enicore\Maris
 */
class Controller
{
    /**
     * The layout's view name; set it in derived classes for areas with their own layout (e.g. "admin/layout").
     */
    protected string $layout = 'layout';

    /**
     * The directory of the views that staticPageRoute() sends, e.g. "admin" for the views in src/views/admin.
     */
    protected string $viewDirectory = '';

    public function __construct(protected View $view)
    {
    }

    /**
     * Route handler that sends the layout without content, for RavenSpa to load the page's content into.
     */
    public function layoutRoute(Request $request, Response $response, array $args): Response
    {
        return $this->sendHtml($response, $this->view->getLayoutHtml(null, [], $this->layout));
    }

    /**
     * Route handler for pages that are only a view: the view named like the path ({path} in the route), in
     * $viewDirectory; "home" for an empty path.
     */
    public function staticPageRoute(Request $request, Response $response, array $args): Response
    {
        $view = ltrim(trim($this->viewDirectory, '/') . '/' . (trim($args['path'] ?? '', '/') ?: 'home'), '/');

        if (!$this->view->viewExists($view)) {
            return $this->notFoundRoute($request, $response, $args);
        }

        return $this->sendPage($request, $response, $view);
    }

    /**
     * Route handler for the 404 page; it's the error view, so RavenSpa shows it as the page's content.
     */
    public function notFoundRoute(Request $request, Response $response, array $args): Response
    {
        return $this->sendPage($request, $response, 'error', ['code' => 404, 'message' => 'Page not found'],
            'Page not found', 404);
    }

    /**
     * Sends a page: the whole page (layout and view) for GET requests, the RavenSpa JSON for other requests.
     *
     * @param Request $request
     * @param Response $response
     * @param string $view The view's name.
     * @param array $data Variables for the view (and the layout); RavenSpa puts them in window.spa_page_data.
     * @param string $title The page title; the view (and the layout) get it as $title, unless the data has one.
     * @param int $code The HTTP status code of the whole page (the JSON is always 200, so RavenSpa shows the view).
     * @return Response
     */
    protected function sendPage(Request $request, Response $response, string $view, array $data = [],
                                string $title = '', int $code = 200): Response
    {
        if ($request->getMethod() === 'GET') {
            return $this->sendHtml(
                $response,
                $this->view->getLayoutHtml($view, $data + ['title' => $title], $this->layout),
                $code
            );
        }

        return $this->sendSuccess($response, [
            'title' => $title,
            'html' => $this->view->getViewHtml($view, $data + ['title' => $title]),
            'data' => $data,
        ]);
    }

    /**
     * Sends a view on its own, without the layout (e.g. a login page).
     */
    protected function sendView(Response $response, string $view, array $data = [], int $code = 200): Response
    {
        return $this->sendHtml($response, $this->view->getViewHtml($view, $data), $code);
    }

    /**
     * Sends a success JSON response: {"success": true, "data": {...}}.
     */
    protected function sendSuccess(Response $response, array $data = [], int $code = 200): Response
    {
        return Http::success($response, $data, $code);
    }

    /**
     * Sends an error JSON response: {"success": false, "error": "message"}. The status code is 200 by default, so the
     * frontend reads the message from the "success" flag; pass a code for a real HTTP error.
     */
    protected function sendError(Response $response, string $message, int $code = 200, array $extra = []): Response
    {
        return Http::error($response, $message, $code, $extra);
    }

    /**
     * Sends a JSON response with any payload.
     */
    protected function sendJson(Response $response, array $payload, int $code = 200): Response
    {
        return Http::json($response, $payload, $code);
    }

    /**
     * Sends an HTML response.
     */
    protected function sendHtml(Response $response, string $html, int $code = 200): Response
    {
        $response->getBody()->write($html);

        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Sends a plain text response.
     */
    protected function sendText(Response $response, string $text, int $code = 200): Response
    {
        $response->getBody()->write($text);

        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Sends a redirect.
     */
    protected function sendRedirect(Response $response, string $url, int $code = 302): Response
    {
        return $response->withHeader('Location', $url)->withStatus($code);
    }

    /**
     * Sends an error page: the error view in the layout, with the code and message.
     */
    protected function sendErrorPage(Response $response, int $code, string $message = ''): Response
    {
        if ($message === '') {
            $message = $code === 404 ? 'Page not found.' : 'Server error.';
        }

        $html = $this->view->getLayoutHtml('error', ['code' => $code, 'message' => $message, 'title' => $message],
            $this->layout);

        return $this->sendHtml($response, $html, $code);
    }
}
