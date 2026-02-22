<?php

namespace App\Service;

use App\DTO\TaskCreateDTO;
use App\DTO\TaskPatchDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Model\TaskStatusEnum;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TaskService{

    public function __construct(
        private ValidatorInterface $validator,
        private TaskRepository $taskRepository,
        private EntityManagerInterface $emi,
    )
    {}

    public function validationTask(TaskCreateDTO|TaskPatchDTO|Task $task): array{
        $errors = $this->validator->validate($task);

        $formattedErrors = [];
        
        if(count($errors) > 0){
            foreach($errors as $error){
                $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
            }
        }

        return $formattedErrors;
    }

    public function newTask(TaskCreateDTO $datas, User $user): Task|JsonResponse{
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

    public function existingTask(int $id): ?Task{
        return $this->taskRepository->findOneById($id);
    }

    public function editTask(Task $task, TaskPatchDTO $dto): Task|JsonResponse{

        $task->setStatus(TaskStatusEnum::from($dto->status));

        $errors = $this->validationTask($task);
        if($errors){
            return new JsonResponse([
                'errors' => $errors
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
        } else{
            $this->emi->flush();
            return $task;
        }
    }

    public function removeTask(Task $task): void{
        $this->emi->remove($task);
        $this->emi->flush();
    }
}