<?php

namespace App\Controller\API;

use App\DTO\TaskDTO;
use App\Service\AuthService;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name:'api_')]
#[IsGranted('ROLE_USER')]
final class TaskController extends AbstractController
{
    #[Route('/tasks', name:'task', methods:['GET'])]
    public function index()
    {
    }

    #[Route('/tasks/create', name:'task_create', methods:['POST'])]
    public function create(Request $request, SerializerInterface $serializer, AuthService $authService, TaskService $taskService): JsonResponse{

        // Check if the user is authenticated and exists in the database
        if(!$authService->existingUser($this->getUser())){
            return $this->json([
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        try{

            // Validate the input data
            $dto = $serializer->deserialize($request->getContent(), TaskDTO::class, 'json');
            $errors = $taskService->validationTask($dto);
            if($errors){
                return $this->json([
                    'errors' => $errors
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Save the new task
            $task = $taskService->newTask($dto, $this->getUser());

            if($task instanceof JsonResponse){
                return $task; // Return the error response if validation failed
            } else{
                return $this->json([
                    'message' => 'New task created successfully',
                    'task' => $task
                ], Response::HTTP_CREATED, [], ['groups' => 'task:read']); // 201 Created
            }
    

        } catch(\Exception $e){

            return $this->json([
                'message' => 'An error occurred while creating the task',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error

        }

    }
}
