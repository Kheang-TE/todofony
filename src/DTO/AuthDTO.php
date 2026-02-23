<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AuthDTO{

    #[Assert\Email()]
    #[Assert\NotBlank()]
    public ?string $email = null;

    #[Assert\NotBlank()]
    public ?string $password = null;

     #[Assert\NotBlank()]
    public ?string $confirmPassword = null;

}