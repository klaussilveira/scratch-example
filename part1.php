<?php

require 'vendor/autoload.php';

use Symfony\Component\{Routing, HttpFoundation};

// Context
$request = HttpFoundation\Request::createFromGlobals();
$context = (new Routing\RequestContext())->fromRequest($request);

// Configuration
$routes = new Routing\RouteCollection();
$routes->add('post', new Routing\Route('/post/{id}'));
$routes->add('home', new Routing\Route('/'));

$app = [
    'post' => function () {
        return new HttpFoundation\Response('Lorem ipsum!');
    },

    'home' => function () {
        return new HttpFoundation\Response('foo');
    },
];

// Kernel
$matcher = new Routing\Matcher\UrlMatcher($routes, $context);
$params = $matcher->matchRequest($request);
$route = $params['_route'];

if (isset($app[$route])) {
    $response = $app[$route]();
} else {
    $response = new HttpFoundation\Response('Not found', 404);
}

$response->send();
