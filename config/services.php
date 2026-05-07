<?php

use Respatch\RespatchBundle\Cache\ResponseSchemaWarmer;
use Respatch\RespatchBundle\Controller\ApiController;
use Respatch\RespatchBundle\EventListener\ResponseSchemaListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container
        ->services()
            ->set(\Respatch\RespatchBundle\Controller\ApiController::class)
                ->autowire()
                ->autoconfigure()
                ->tag('controller.service_arguments')
                
            ->set(\Respatch\RespatchBundle\Helper\ApiHelper::class)
                ->autowire()
                ->autoconfigure()

            ->set('respatch.authenticator', \Respatch\RespatchBundle\Security\RespatchTokenAuthenticator::class)
                ->arg('$configuredToken', '%respatch.token%')
                ->alias(\Respatch\RespatchBundle\Security\RespatchTokenAuthenticator::class, 'respatch.authenticator')

            ->set(ResponseSchemaWarmer::class)
                ->arg('$controllerClasses', [ApiController::class])
                ->tag('kernel.cache_warmer')

            ->set(ResponseSchemaListener::class)
                ->arg('$cacheDir', '%kernel.cache_dir%')
                ->arg('$environment', '%kernel.environment%')
                ->autowire()
                ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse'])
    ;
};
