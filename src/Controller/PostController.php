<?php

namespace Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostController
{
    #[Route('/post/{id}')]
    public function index(Request $request)
    {
        return new Response('Post #' . $request->attributes->get('id'));
    }
}
