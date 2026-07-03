<?php

namespace App;

enum TaskStatusEnum: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in progress';
    case DONE = 'done';
}
