<?php

namespace App\Controller\API;

use App\DTO\RegisterUserDTO;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name:'api_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods:['POST'])]
    public function register(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, AuthService $authService): JsonResponse
    {
        try{

            // Deserialize JSON to DTO
            $dto = $serializer->deserialize($request->getContent(), RegisterUserDTO::class, 'json');

        } catch(\Exception $e){

            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);

        }

        // Datas DTO Validation 
        $errors = $validator->validate($dto);

        if(count($errors) > 0){

            $formattedErrors = [];

            foreach($errors as $error){
                $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'errors' => $formattedErrors
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
        ], Response::HTTP_CREATED);
    }
}
