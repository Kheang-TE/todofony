<?php

namespace App\Controller\API;

use App\DTO\TaskCreateDTO;
use App\DTO\TaskPatchDTO;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\AuthService;
use App\Service\TaskService;
use OpenApi\Attributes as OA;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name:'api_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{

    public function __construct(
        private AuthService $authService,
        private SerializerInterface $serializer,
        private TaskService $taskService
    )
    {}

    #[Route('/tasks', name:'task', methods:['GET'])]
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Obtenir toutes les tâches',
        tags: ['Tasks'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des tâches',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Faire les courses'),
                            new OA\Property(property: 'status', type: 'string', enum: ['todo', 'doing', 'done'], example: 'todo'),
                            new OA\Property(property: 'person', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com')
                            ]),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function allTasks(TaskRepository $taskRepository): JsonResponse
    {
        // Check if the user is authenticated and exists in the database
        if(!$this->authService->existingUser($this->getUser()) || !$this->getUser() instanceof User){
            return $this->json([
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        try{

            $user = $this->getUser();
            $tasks = $taskRepository->findBy(
                ['person' => $user],
                ['updatedAt' => 'DESC']
            );
            return $this->json(
                $tasks, 
                Response::HTTP_OK, 
                [], 
                ['groups' => 'task:read']
            ); // 200 OK

        } catch(\Exception $e){

            return $this->json([
                'message' => 'An error occurred while retrieving tasks',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error

        }

    }

    #[Route('/tasks/create', name:'task_create', methods:['POST'])]
    #[OA\Post(
        path:'/api/tasks/create',
        summary: 'Créer une nouvelle tâche',
        tags: ['Tasks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Faire les courses')
                ],
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tâche créée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'task', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Faire les courses'),
                            new OA\Property(property: 'status', type: 'string', example: 'todo'),
                            new OA\Property(property: 'person', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com')
                            ])
                        ])
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function create(Request $request): JsonResponse{

        // Check if the user is authenticated and exists in the database
        if(!$this->authService->existingUser($this->getUser())){
            return $this->json([
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        try{

            // Validate the input data
            $dto = $this->serializer->deserialize($request->getContent(), TaskCreateDTO::class, 'json');
            $errors = $this->taskService->validationTask($dto);
            if($errors){
                return $this->json([
                    'errors' => $errors
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Save the new task
            $task = $this->taskService->newTask($dto, $this->getUser());

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

    #[Route('/tasks/edit/{id}', name:'task_edit', methods:['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/tasks/edit/{id}',
        summary: 'Modifier une tâche existante',
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID de la tâche à modifier',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Faire les courses'),
                    new OA\Property(property: 'status', type: 'string', enum: ['todo', 'doing', 'done'], example: 'doing')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tâche modifiée avec succès',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'task', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'title', type: 'string', example: 'Faire les courses'),
                            new OA\Property(property: 'status', type: 'string', example: 'doing'),
                            new OA\Property(property: 'person', type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com')
                            ])
                        ])
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Tâche non trouvée'),
            new OA\Response(response: 403, description: 'Non autorisé à modifier cette tâche'),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function edit(Request $request): JsonResponse{

        // Check if the user is authenticated and exists in the database
        if(!$this->authService->existingUser($this->getUser()) || !$this->getUser() instanceof User){
            return $this->json([
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        $user = $this->getUser();

        try{

            // Get the task ID from the route parameters
            $id = $request->attributes->get('id');

            // Validate the input data
            $dto = $this->serializer->deserialize($request->getContent(), TaskPatchDTO::class, 'json');
            $errors = $this->taskService->validationTask($dto);
            if($errors){
                return $this->json([
                    'errors' => $errors
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }
            
            // Check if the task exists
            $task = $this->taskService->existingTask($id);
            if(!$task){
                return $this->json([
                    'message' => 'Task not found'
                ], Response::HTTP_NOT_FOUND); // 404 Not Found
            }

            // Check if the task belongs to the authenticated user
            if($task->getPerson()->getId() !== $user->getId()){
                return $this->json([
                    'message' => 'You are not authorized to edit this task'
                ], Response::HTTP_FORBIDDEN); // 403 Forbidden
            }

            // Edit the task
            $editTask = $this->taskService->editTask($task, $dto);
            if($editTask instanceof JsonResponse){
                return $editTask; // Return the error response if validation failed
            } else{
                return $this->json([
                    'message' => 'Task edited successfully',
                    'task' => $editTask
                ], Response::HTTP_OK, [], ['groups' => 'task:read']); // 200 OK
            }

        } catch(\Exception $e){

            return $this->json([
                'message' => 'An error occurred while editing the task',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error

        }

    }

    #[Route('/tasks/remove/{id}', name:'task_remove', methods:['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/tasks/remove/{id}',
        summary: 'Supprimer une tâche existante',
        tags: ['Tasks'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID de la tâche à supprimer',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tâche supprimée avec succès'),
            new OA\Response(response: 404, description: 'Tâche non trouvée'),
            new OA\Response(response: 403, description: 'Non autorisé à supprimer cette tâche'),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function remove(Request $request): JsonResponse{

        // Check if the user is authenticated and exists in the database
        if(!$this->authService->existingUser($this->getUser()) || !$this->getUser() instanceof User){
            return $this->json([
                'message' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        $user = $this->getUser();

        try{

            // Get the task ID from the route parameters
            $id = $request->attributes->get('id');

            // Check if the task exists
            $task = $this->taskService->existingTask($id);
            if(!$task){
                return $this->json([
                    'message' => 'Task not found'
                ], Response::HTTP_NOT_FOUND); // 404 Not Found
            }

            // Check if the task belongs to the authenticated user
            if($task->getPerson()->getId() !== $user->getId()){
                return $this->json([
                    'message' => 'You are not authorized to remove this task'
                ], Response::HTTP_FORBIDDEN); // 403 Forbidden
            }

            // Remove the task
            $this->taskService->removeTask($task);

            return $this->json([
                'message' => 'Task removed successfully'
            ], Response::HTTP_OK); // 200 OK

        } catch(\Exception $e){

            return $this->json([
                'message' => 'An error occurred while removing the task',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error

        }

    }
}
