<?php

namespace App\Service;

use App\DTO\RegisterUserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService{

    public function __construct(
        private EntityManagerInterface $emi
    ){}

    public function registerUser(RegisterUserDTO $dto, UserPasswordHasherInterface $hasher, ):User{

        $user = new User();
        
        $user->setEmail($dto->email);
        
        $hashPwd = $hasher->hashPassword($user, $dto->password);
        $user->setPassword($hashPwd);

        $user->setRoles(['ROLE_USER']);

        $this->emi->persist($user);
        $this->emi->flush();

        return $user;
    }

}