# Enicore Maris

Enicore Maris is a collection of PHP utility classes for building websites with [Slim](https://www.slimframework.com/)
and the RavenCSS frontend (with its RavenSpa navigation): controllers, middleware, views, configuration, input
validation and sessions, plus general utilities (encryption, files, text, random data, countries, currencies,
languages, locales and timezones). Classes for websites without Slim are in the Standalone namespace.

## Installation

```shell
composer require enicore/maris
```

To copy from a local directory, add the following configuration in your composer.json:

```composer
    "require": {
        "enicore/maris": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/enicore/maris",
            "options": {
                "symlink": true
            }
        }
    ]
```

Maris requires PHP 8.3. The Slim classes need `slim/slim` and `slim/psr7` (Maris doesn't install them).

## Namespaces

| Namespace                     | Contents                                                                                 |
|-------------------------------|------------------------------------------------------------------------------------------|
| `Enicore\Maris\Classes`       | Config, Session, View, Input, Http, RouteUrl, Code, Disk, Text, Web, Random              |
| `Enicore\Maris\Controllers`   | Controller (pages and JSON responses), SlimErrorController                               |
| `Enicore\Maris\Middleware`    | Session, Auth, Csrf, Csp, SecurityHeaders and HostRestriction middleware                 |
| `Enicore\Maris\Resources`     | Countries, Currencies, Languages, Locales, Timezones                                     |
| `Enicore\Maris\Standalone`    | For websites without Slim: Database, Model, Form, Auth, Request, Response, Di            |

`src/Functions/web.php` adds the view functions `w_url()`, `w_script()`, `w_style()`, `w_icon()` and `w_image()`,
which add a version to the asset URLs, so browsers load the new file after a change.

## Building a Slim website

A typical site: an admin area that RavenSpa navigates (the layout is loaded once, then each page's content with a
POST request), a login page, and JSON actions called with `fetch()`.

```
public/index.php          the app
src/Controllers/          the site's controllers, derived from Maris' Controller
src/views/layout.php      the page layout
src/views/error.php       the content of error pages
src/views/...             the pages' content
.env                      the configuration (not in git)
```

### public/index.php

```php
<?php
use DI\ContainerBuilder;
use Enicore\Maris\Classes\Config;
use Enicore\Maris\Classes\RouteUrl;
use Enicore\Maris\Controllers\SlimErrorController;
use Enicore\Maris\Middleware\AuthMiddleware;
use Enicore\Maris\Middleware\CspMiddleware;
use Enicore\Maris\Middleware\CsrfMiddleware;
use Enicore\Maris\Middleware\SecurityHeadersMiddleware;
use Enicore\Maris\Middleware\SessionMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// the configuration, from the server's environment or the .env file; a missing value stops the app
try {
    $config = Config::fromEnv([
        'db_host' => 'string',
        'db_port' => ['type' => 'int', 'default' => 3306],
        'db_database' => 'string',
        'db_username' => 'string',
        'db_password' => 'string',
        'debug' => ['type' => 'bool', 'default' => false],
    ], __DIR__ . '/../.env');
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    exit('Configuration error');
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    Config::class => $config,
    // Auth::class => ..., the authentication library (e.g. delight-im/auth)
]);
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();
$container->get(RouteUrl::class)->setRouteParser($app->getRouteCollector()->getRouteParser());

// Slim runs the middleware added last first
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware($config->get('debug'), true, true)->setDefaultErrorHandler(SlimErrorController::class);
// after the error middleware, so the error pages get the headers too
$app->add(new CspMiddleware("default-src 'self'; script-src 'self' 'unsafe-eval'; object-src 'none'; " .
    "base-uri 'self'; form-action 'self'; frame-ancestors 'none';"));
$app->add(new SecurityHeadersMiddleware(hstsMaxAge: 31536000));

$app->group('', function ($group) use ($container) {
    $group->get('/login', [AuthController::class, 'loginPage'])->setName('login');
    $group->post('/login', [AuthController::class, 'login']);

    $group->group('/admin', function ($group) {
        $group->post('', [DashboardController::class, 'page']);
        $group->post('/users/save', [UsersController::class, 'save']);
        $group->post('/{path:.+}', [AdminController::class, 'staticPageRoute']);
        $group->get('[/{path:.*}]', [AdminController::class, 'layoutRoute'])->setName('admin');
    })
        ->add(CsrfMiddleware::class)
        ->add(new AuthMiddleware(fn() => $container->get(Auth::class)->isLoggedIn(), 'login'));
})->add(new SessionMiddleware());

$app->run();
```

Only the routes in the group with `SessionMiddleware` get a session; others (APIs, webhooks, error pages for bots)
don't create session files.

### Controllers and views

The site's controllers extend `Controller`, which renders the views (`View`, from `src/views`) and sends the
responses:

```php
class AdminController extends Controller
{
    // the area's layout: src/views/admin/layout.php
    protected string $layout = 'admin/layout';
    // staticPageRoute() sends the views in src/views/admin
    protected string $viewDirectory = 'admin';
}

class DashboardController extends AdminController
{
    public function __construct(View $view, private Connection $db)
    {
        parent::__construct($view);
    }

    // RavenSpa's request for the page's content
    public function page(Request $request, Response $response): Response
    {
        $users = $this->db->table('users')->count();
        return $this->sendPage($request, $response, 'admin/dashboard', ['users' => $users], 'Dashboard');
    }
}
```

- `sendPage()` sends RavenSpa's JSON (`{"success": true, "data": {"title", "html", "data"}}`); the view gets the data
  as variables, and RavenSpa puts the data in `window.spa_page_data` for the page's scripts. A GET request gets the
  whole page (the layout with the view), so pages also work without RavenSpa.
- `layoutRoute()` sends the layout without content, for RavenSpa to load the content into; `staticPageRoute()` sends
  the view named like the path (`{path}`, in `$viewDirectory`, "home" for an empty path), or the 404 page;
  `notFoundRoute()` sends the 404 page.
- `sendSuccess()` and `sendError()` send the JSON responses of actions: `{"success": true, "data": {...}}` and
  `{"success": false, "error": "message"}` (status 200 unless given, so the frontend reads the "success" flag).
- `sendView()` sends a view without the layout (e.g. the login page), `sendHtml()`, `sendText()`, `sendJson()`,
  `sendRedirect()` and `sendErrorPage()` the other responses.

The layout gets `$content` (the page's view), `$title`, the page's data, and `$csrf`, the session's CSRF token, which
RavenSpa sends with its requests:

```php
<meta name="csrf" content="<?= $csrf ?>">
<title><?= htmlspecialchars($title ?? '') ?></title>
<?= w_style('/build/admin.css') ?>
<?= w_script('/build/admin.js') ?>
...
<div id="content"><?= $content ?></div>
```

`error.php` is the content of error pages, with `$code` and `$message`. `SlimErrorController` renders them in
`layout.php`; extend it and set `$layout` for another layout.

`SlimErrorController` handles Slim's errors: JSON errors for requests that expect JSON (`fetch()` calls with
`Accept: application/json` or `X-Requested-With: XMLHttpRequest`, RavenSpa's page loads), error pages otherwise.
Errors that aren't HTTP errors (bugs) are logged and shown as "Server error.", with the message only if Slim's
`displayErrorDetails` is on.

### Input

`Input` reads the posted values (`fromBody()`, JSON or form) or the query string (`fromQuery()`), converted and
validated. The getters throw an `InputException`, with a message that can be shown to the user, if a value is
missing (unless a default is given) or invalid:

```php
public function save(Request $request, Response $response): Response
{
    try {
        $input = Input::fromBody($request);
        $name = $input->getString('name', maxLength: 100, allowEmpty: false);
        $email = $input->getEmail('email');
        $age = $input->getInt('age', default: 0);
        $active = $input->getBool('active', default: false); // unchecked checkboxes send nothing
        $tags = $input->getArray('tags', default: []);
    } catch (InputException $e) {
        return $this->sendError($response, $e->getMessage(), 400);
    }
    ...
    return $this->sendSuccess($response, ['id' => $id]);
}
```

### Configuration

`Config::fromEnv()` loads values from the environment variables (the uppercase keys), converted to their types
(`string`, `int`, `float`, `bool`); values without a default are required. The `.env` file, if given, fills in the
variables the server doesn't set:

```shell
# .env
DB_HOST=localhost
DB_PASSWORD="secret # with a hash"
DEBUG=true
```

`new Config($file)` loads a PHP file that returns an array instead; `loadEnv()` adds environment values to it.

### Sessions, CSRF and logins

- `SessionMiddleware` starts the session. Its cookie is secure (https only; browsers allow it on `localhost`),
  HTTP-only and `SameSite=Lax`; change the options with `new SessionMiddleware(['name' => 'admin', 'lifetime' =>
  86400])` or `Session::setOptions()`. `Session` reads and writes the session's values.
- `CsrfMiddleware` checks the CSRF token of the requests that can change data (all but GET, HEAD, OPTIONS): the
  `X-CSRF-Token` header (RavenSpa sends it), or a `csrf` field in posted forms
  (`<input type="hidden" name="csrf" value="<?= $csrf ?>">`).
- `AuthMiddleware` lets logged-in users through; it gets a function that checks the login, so it works with any
  authentication library. Others are redirected to the login page (a route name, or a path starting with "/"), or
  get a 401 JSON error with the login URL in `redirect` if the request expects JSON.

### Security headers

- `CspMiddleware` sets the Content-Security-Policy; the default allows only the site's own files (no inline scripts
  or styles, no frames). Alpine's standard build needs `'unsafe-eval'` in `script-src` (its CSP build doesn't).
- `SecurityHeadersMiddleware` sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Cross-Origin-Opener-Policy` and `Permissions-Policy`, and `Strict-Transport-Security` on https requests if
  `hstsMaxAge` is set (browsers then use only https for the site, so set it only on sites that stay on https).
  Headers can be replaced or removed (`null`).
- `HostRestrictionMiddleware` answers 404 to other host names than the allowed ones (e.g. the subdomains cPanel
  creates).

The middleware don't replace headers that a route's response already has.

### URLs of named routes

`RouteUrl` builds the URLs of named routes in controllers (inject it): `$this->routeUrl->get('user', ['id' => 5])`.
Its route parser is set in index.php, after the app is created.

## Websites without Slim

The Standalone classes work with PHP's request globals: `Request` (GET, POST and JSON values), `Response` (JSON
responses that end the script), `Database` (PDO for MySQL), `Model` (records), `Form`, `Auth` (logins in the
session) and `Di` (static access to the services of a PSR-11 container, which `Model` and `Form` use).

## Tests

```shell
vendor/bin/phpunit
```

The Database, Model and Form tests need a MySQL database, set with environment variables; without
`MARIS_TEST_DB_HOST` they're skipped. The tests create and drop their own tables.

```shell
MARIS_TEST_DB_HOST=localhost MARIS_TEST_DB_PORT=3306 MARIS_TEST_DB_NAME=maris_test \
MARIS_TEST_DB_USER=root MARIS_TEST_DB_PASSWORD=secret vendor/bin/phpunit
```

## License

Enicore Maris is licensed under the MIT License. See LICENSE for more information.
