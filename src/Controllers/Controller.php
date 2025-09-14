<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Enicore\Maris\Classes\View;

class Controller
{
    public function __construct(protected View $view)
    {
    }

    public function staticPageRoute(Request $request, Response $response, array $args): Response
    {
        return $this->sendPage($request, $response, $args['path'] ?? '');
    }

    public function notFoundRoute(Request $request, Response $response, array $args): Response
    {
        return $this->sendPage($request, $response, 'error', ['code' => 404, 'message' => 'Page not found']);
    }

    protected function sendPage(Request $request, Response $response, string $view, array $data = []): Response
    {
        return $request->getMethod() === 'GET' ?
            $this->sendText($response, $this->view->getLayoutHtml($view, $data)) :
            $this->sendJson($response, ['html' => $this->view->getViewHtml($view, $data), 'data' => $data]);
    }

    protected function sendJson(Response $response, array $payload, int $code = 200): Response
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            return $this->sendError($response, 500, 'JSON encoding failed (384992)');
        }

        $response = $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        $response->getBody()->write($json);
        return $response;
    }

    protected function sendText(Response $response, string $text, int $code = 200): Response
    {
        $response = $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        $response->getBody()->write($text);
        return $response;
    }

    protected function sendError(Response $response, int $code, string $message = ''): Response
    {
        if ($message === '') {
            $message = $code === 404 ? 'Page not found.' : 'Server error.';
        }

        $html = $this->view->getLayoutHtml('error', ['code' => $code, 'message' => $message]);
        return $this->sendText($response, $html, $code);
    }

}