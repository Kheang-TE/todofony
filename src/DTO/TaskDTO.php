<?php

namespace App\DTO;

use App\Model\TaskStatusEnum;

class TaskDTO{

    public ?string $title = null;
    public TaskStatusEnum $status = TaskStatusEnum::TODO;

}