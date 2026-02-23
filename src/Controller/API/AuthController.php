<?php

namespace App\Controller\API;

use App\DTO\AuthDTO;
use App\Service\AuthService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name:'api_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name:'register', methods:['POST'])]
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
    public function login(): JsonResponse{

        return $this->json([
            'error' => 'Verify the firewall configuration. The login path is not properly defined.'
        ], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized

    }

    #[Route('/logout', name:'logout', methods:['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse([
            'message' => 'Successfully logged out'
        ], Response::HTTP_OK); // 200 OK

        // Clear the authentication cookie
        $response->headers->clearCookie('BEARER');

        return $response;
    }

}
