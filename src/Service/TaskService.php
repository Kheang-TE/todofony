<?php

namespace App\Service;

use App\DTO\TaskDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Model\TaskStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TaskService{

    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $emi
    )
    {}

    public function validationTask(TaskDTO|Task $task): array{
        $errors = $this->validator->validate($task);

        $formattedErrors = [];
        
        if(count($errors) > 0){
            foreach($errors as $error){
                $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
            }
        }

        return $formattedErrors;
    }

    public function newTask(TaskDTO $datas, User $user): Task|JsonResponse{
        $task = new Task();
        $task->setTitle($datas->title);
        $task->setStatus(TaskStatusEnum::TODO);
        $task->setPerson($user);

        $errors = $this->validationTask($task);
        if($errors){
            return new JsonResponse([
                'errors' => $errors
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
        } else{
            $this->emi->persist($task);
            $this->emi->flush();
    
            return $task;
        }

    }
}