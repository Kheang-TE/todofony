<?php

namespace App\Controller\API;

use App\DTO\AuthDTO;
use App\Service\AuthService;
use OpenApi\Attributes as OA;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name:'api_')]
#[OA\Tag(name: 'Authentification')]
final class AuthController extends AbstractController
{
    #[Route('/register', name:'register', methods:['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Inscription d\'un nouvel utilisateur',
        tags: ['Authentification'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'confirmPassword'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'confirmPassword', type: 'string', format: 'password', example: 'password123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'New user successfully registered'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                            new OA\Property(property: 'role', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER'])
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Format JSON invalide'),
            new OA\Response(response: 409, description: 'Email déjà existant'),
            new OA\Response(response: 422, description: 'Erreurs de validation des données')
        ]
    )]
    public function register(Request $request, SerializerInterface $serializer, AuthService $authService): JsonResponse
    {
        try{

            // Deserialize JSON to DTO
            $dto = $serializer->deserialize($request->getContent(), AuthDTO::class, 'json');

            $existingUser = $authService->existingUser($dto);
            if($existingUser){
                return $this->json([
                    'error' => 'Email already exists'
                ], Response::HTTP_CONFLICT); // 409 Conflict
            }

        } catch(\Exception $e){

            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST); // 400 Bad Request

        }

        // Datas DTO Validation
        $errors = $authService->validationUser($dto);
        if($errors){
            return $this->json([
                'errors' => $errors
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
        }

        if($dto->password !== $dto->confirmPassword){
            return $this->json([
                'error' => 'Password and confirm password do not match'
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
        }

        // Use Authservice->registerUser for save new user
        $user = $authService->registerUser($dto);

        return $this->json([
            'message' => 'New user successfully registered',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRoles()
            ]
        ], Response::HTTP_CREATED); // 201 Created
    }

    // The login route is handled by the security firewall, so we just return an error message here.
    #[Route('/login', name:'login', methods:['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Connexion d\'un utilisateur',
        tags: ['Authentification'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                            new OA\Property(property: 'role', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER'])
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides')
        ]
    )]
    public function login(): JsonResponse{

        return $this->json([
            'error' => 'Verify the firewall configuration. The login path is not properly defined.'
        ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized

    }

    #[Route('/logout', name:'logout', methods:['POST'])]
    #[OA\Post(
        path: '/api/logout',
        summary: 'Déconnexion de l\'utilisateur',
        tags: ['Authentification'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Déconnexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully logged out')
                    ]
                )
            )
        ]
    )]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse([
            'message' => 'Successfully logged out'
        ], Response::HTTP_OK); // 200 OK

        // Clear the authentication cookie
        $response->headers->clearCookie('BEARER');

        return $response;
    }

    #[Route('/me', name:'me', methods:['GET'])]
    #[OA\Get(
        path: '/api/me',
        summary: 'Obtenir les informations de l\'utilisateur connecté',
        tags: ['Authentification'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Informations de l\'utilisateur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER'])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Utilisateur non authorisé')
        ]
    )]
    public function me():JsonResponse{
        $user = $this->getUser();

        if(!$user){
            return $this->json([
                'error' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        return $this->json([
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles()
        ], Response::HTTP_OK); // 200 OK
    }

}
