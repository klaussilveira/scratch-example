<?php

require 'vendor/autoload.php';

use Symfony\Component\{Routing, HttpFoundation, Config};

// Context
$request = HttpFoundation\Request::createFromGlobals();
$context = (new Routing\RequestContext())->fromRequest($request);

// Configuration
class CustomLoader extends Routing\Loader\AnnotationClassLoader
{
    protected function configureRoute(Routing\Route $route, ReflectionClass $class, ReflectionMethod $method, object $annot)
    {
        $route->setDefault('_controller', $class->getName());
        $route->setDefault('_action', $method->getName());
    }
}

$loader = new Routing\Loader\AnnotationDirectoryLoader(
    new Config\FileLocator(__DIR__),
    new CustomLoader(),
);
$routes = $loader->load('src/Controller');

// Kernel
$matcher = new Routing\Matcher\UrlMatcher($routes, $context);
$params = $matcher->matchRequest($request);
$request->attributes->add($params);
$controller = new $params['_controller'];
$action = $params['_action'];

try {
    $response = $controller->$action($request);
} catch (Throwable $e) {
    $response = new HttpFoundation\Response('Not found', 404);
}

$response->send();
