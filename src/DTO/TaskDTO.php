<?php

namespace App\DTO;

use App\Model\TaskStatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

class TaskDTO{

    #[Assert\NotBlank()]
    public ?string $title = null;

    #[Assert\NotBlank()]
    public TaskStatusEnum $status = TaskStatusEnum::TODO;

}