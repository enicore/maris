<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Controllers;

use Enicore\Maris\Classes\Http;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpException;
use Throwable;

/**
 * Slim's error handler: JSON errors for requests that expect JSON (fetch() calls, RavenSpa's page loads), the error
 * page otherwise. Errors that aren't HTTP errors (404, 405...) are 500 errors and are logged.
 *
 * $app->addErrorMiddleware(false, true, true)->setDefaultErrorHandler(SlimErrorController::class);
 *
 * @package Enicore\Maris
 */
class SlimErrorController extends Controller
{
    public function __invoke(Request $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors,
                             bool $logErrorDetails): Response
    {
        $isHttpError = $exception instanceof HttpException && $exception->getCode() >= 400;
        $code = $isHttpError ? $exception->getCode() : 500;

        if (!$isHttpError) {
            error_log((string)$exception);
        }

        // HTTP errors have a message for the user ("Not found."), the other errors' messages are for the log only
        $message = $isHttpError ? ($exception->getMessage() ?: $exception->getTitle()) : 'Server error.';

        if ($displayErrorDetails && !$isHttpError) {
            $message .= ' ' . $exception->getMessage();
        }

        $response = new \Slim\Psr7\Response();

        if (Http::wantsJson($request)) {
            return $this->sendError($response, $message, $code);
        }

        return $this->sendErrorPage($response, $code, $message);
    }
}
