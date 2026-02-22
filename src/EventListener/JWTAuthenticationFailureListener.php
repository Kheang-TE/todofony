<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure')]
class JWTAuthenticationFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event){

        $response = new JsonResponse([
            'error' => 'Authentication failed. Invalid credentials.'
        ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        
        $event->setResponse($response);
    }
}