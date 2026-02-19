<?php

namespace App\EventListener;

use App\Entity\User;

use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;

#[AsDoctrineListener(event: Events::prePersist)]
class EntityListener{

    public function prePersist(PrePersistEventArgs $args)
    {

        $entity = $args->getObject();

        $entities = [User::class];

        // control authorized Classes
        if(!in_array(get_class($entity), $entities)){
            return;
        }

        // update the updatedAt field
        $entity->setUpdatedAt(new \DateTimeImmutable());

        // set createdAt field if new insert
        if(!$entity->getId()){
            $entity->setCreatedAt(new \DateTimeImmutable());
        }

    }

}