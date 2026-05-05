<?php

namespace Respatch\RespatchBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HelloController
{
    public function __invoke(Request $request): Response
    {
        return new Response('Hello RespatchBundle!');
    }
}
