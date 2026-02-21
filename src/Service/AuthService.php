<?php

namespace App\Service;

use App\DTO\AuthDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthService{

    public function __construct(
        private ValidatorInterface $validator,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $hasher,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $emi
    ){}

    public function validationUser(AuthDTO $dto): array{
        $errors = $this->validator->validate($dto);

        $formattedErrors = [];
        
        if(count($errors) > 0){
            foreach($errors as $error){
                $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
            }
        }

        return $formattedErrors;
    }

    public function existingUser(AuthDTO|User $user): ?User{
        if($user instanceof AuthDTO){
            return $this->userRepository->findOneBy(['email' => strtolower(trim($user->email))]);
        } else if($user instanceof User){
            return $this->userRepository->findOneBy(['email' => strtolower(trim($user->getEmail()))]);
        }
    }

    public function registerUser(AuthDTO $dto):User{

        $user = new User();
        
        $user->setEmail(strtolower(trim($dto->email)));
        
        $hashPwd = $this->hasher->hashPassword($user, $dto->password);
        $user->setPassword($hashPwd);

        $user->setRoles(['ROLE_USER']);

        $this->emi->persist($user);
        $this->emi->flush();

        return $user;
    }

}