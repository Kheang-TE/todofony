<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TaskCreateDTO{

    #[Assert\NotBlank()]
    public ?string $title = null;

}