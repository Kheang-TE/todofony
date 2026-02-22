<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TaskPatchDTO{

    #[Assert\NotBlank()]
    #[Assert\Choice(['todo', 'doing', 'done'])]
    public string $status;

}