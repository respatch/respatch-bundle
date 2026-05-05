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
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'status'])
            ->methods(['GET'])

        ->add('respatch_api_dashboard', '/dashboard')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'dashboard'])
            ->methods(['GET'])

        ->add('respatch_api_statistics', '/statistics')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'statistics'])
            ->methods(['GET'])

        ->add('respatch_api_history', '/history')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'history'])
            ->methods(['GET'])

        ->add('respatch_api_detail', '/history/{id}')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'detail'])
            ->methods(['GET'])

        ->add('respatch_api_transport', '/transport/{name}')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'transports'])
            ->methods(['GET'])
            ->defaults(['name' => null])

        ->add('respatch_api_transport_remove', '/transport/{name}/{id}/remove')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'removeTransportMessage'])
            ->methods(['POST'])

        ->add('respatch_api_transport_retry', '/transport/{name}/{id}/retry')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'retryFailedMessage'])
            ->methods(['POST'])

        ->add('respatch_api_schedule', '/schedule/{name}')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'schedules'])
            ->methods(['GET'])
            ->defaults(['name' => null])

        ->add('respatch_api_schedule_trigger', '/schedules/{name}/trigger/{id}/{transport}')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'triggerScheduleTask'])
            ->methods(['POST'])

        ->add('respatch_api_workers', '/workers')
            ->controller([\Respatch\RespatchBundle\Controller\ApiController::class, 'workersWidget'])
            ->methods(['GET'])
    ;
};
