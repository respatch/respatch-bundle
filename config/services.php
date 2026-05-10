<?php

use MostkaSk\RespatchBundle\Cache\ResponseSchemaWarmer;
use MostkaSk\RespatchBundle\Controller\ApiController;
use MostkaSk\RespatchBundle\EventListener\ResponseSchemaListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * @link https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container
        ->services()
            ->set(\MostkaSk\RespatchBundle\Controller\ApiController::class)
                ->autowire()
                ->autoconfigure()
                ->tag('controller.service_arguments')
                
            ->set(\MostkaSk\RespatchBundle\Helper\ApiHelper::class)
                ->autowire()
                ->autoconfigure()
                ->arg('$appSecret', '%env(APP_SECRET)%')

            ->set('respatch.authenticator', \MostkaSk\RespatchBundle\Security\RespatchTokenAuthenticator::class)
                ->arg('$configuredToken', '%respatch.token%')
                ->alias(\MostkaSk\RespatchBundle\Security\RespatchTokenAuthenticator::class, 'respatch.authenticator')

    ;
};
