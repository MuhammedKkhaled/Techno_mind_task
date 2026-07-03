<?php

namespace App;

enum TaskStatusEnum: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
}
