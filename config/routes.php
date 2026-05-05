<?php

use Respatch\RespatchBundle\Controller\HelloController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#routing
 */
return static function (RoutingConfigurator $routes): void {
    $routes
        ->add('Respatch_respatch_hello_controller', '/')
            ->controller(HelloController::class)
            ->methods(['GET'])
        ->add('respatch_api_status', '/status')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, '__invoke'])
            ->methods(['GET'])
    ;
};
