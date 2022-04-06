<?php

require 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = [
    '/post' => function () {
        return new Response('Lorem ipsum!');
    },

    '/' => function () {
        return new Response('foo');
    },
];

$request = Request::createFromGlobals();
$path = $request->getPathInfo();
if (isset($app[$path])) {
    $response = $app[$path]();
} else {
    $response = new Response('Not found', 404);
}

$response->send();
