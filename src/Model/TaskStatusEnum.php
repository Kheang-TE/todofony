<?php

namespace App\Model;

enum TaskStatusEnum: string
{
    case TODO = 'todo';
    case DOING = 'doing';
    case DONE = 'done';
}