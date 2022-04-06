<?php

require 'vendor/autoload.php';

use Symfony\Component\{Routing, HttpFoundation, Config, DependencyInjection};

// Context
$request = HttpFoundation\Request::createFromGlobals();
$context = (new Routing\RequestContext())->fromRequest($request);
$configLocator = new Config\FileLocator(__DIR__);

// Container
$cache = 'cache.php';
if (file_exists($cache)) {
    require_once $cache;
    $container = new ProjectServiceContainer();
} else {
    $container = new DependencyInjection\ContainerBuilder();
    $loader = new DependencyInjection\Loader\YamlFileLoader($container, $configLocator);
    $loader->load('services.yaml');
    $container->compile();
    file_put_contents($cache, (new DependencyInjection\Dumper\PhpDumper($container))->dump());
}

// Configuration
class CustomLoader extends Routing\Loader\AnnotationClassLoader
{
    protected function configureRoute(Routing\Route $route, ReflectionClass $class, ReflectionMethod $method, object $annot)
    {
        $route->setDefault('_controller', $class->getName());
        $route->setDefault('_action', $method->getName());
    }
}

$routeCache = 'routes.php';
if (file_exists($routeCache)) {
    $routes = require $routeCache;
    $matcher = new Routing\Matcher\CompiledUrlMatcher($routes, $context);
} else {
    $loader = new Routing\Loader\AnnotationDirectoryLoader(
        $configLocator,
        new CustomLoader(),
    );
    $routes = $loader->load('src/Controller');
    $matcher = new Routing\Matcher\UrlMatcher($routes, $context);
    file_put_contents($routeCache, (new Routing\Matcher\Dumper\CompiledUrlMatcherDumper($routes))->dump());
}

// Kernel
$params = $matcher->matchRequest($request);
$request->attributes->add($params);
$action = $params['_action'];

try {
    $response = $container->get($params['_controller'])->$action($request);
} catch (Throwable $e) {
    $response = new HttpFoundation\Response('Not found', 404);
}

$response->send();
