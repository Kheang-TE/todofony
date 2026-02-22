<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
class JWTAuthenticationSuccessListener
{
    public function __invoke(AuthenticationSuccessEvent $event){

        $event->setData([
            'message' => 'Authentication successful',
            'user' => [
                'email' => $event->getUser()->getUserIdentifier(),
                'roles' => $event->getUser()->getRoles()
            ]
        ]);
    }
}