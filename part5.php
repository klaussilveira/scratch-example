<?php

require 'vendor/autoload.php';

use Symfony\Component\{Routing, HttpFoundation, Config, DependencyInjection};

// Context
$request = HttpFoundation\Request::createFromGlobals();
$context = (new Routing\RequestContext())->fromRequest($request);
$configLocator = new Config\FileLocator(__DIR__);

// Container
$container = new DependencyInjection\ContainerBuilder();
$loader = new DependencyInjection\Loader\YamlFileLoader($container, $configLocator);
$loader->load('services.yaml');
$container->compile();

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
    $configLocator,
    new CustomLoader(),
);
$routes = $loader->load('src/Controller');

// Kernel
$matcher = new Routing\Matcher\UrlMatcher($routes, $context);
$params = $matcher->matchRequest($request);
$request->attributes->add($params);
$action = $params['_action'];

try {
    $response = $container->get($params['_controller'])->$action($request);
} catch (Throwable $e) {
    $response = new HttpFoundation\Response('Not found', 404);
}

$response->send();
