<?php

namespace App;

enum QueueEnum: string
{
    case EMAILS = 'emails';
    case DEFAULT = 'default';

    public function getQueueName(): string
    {
        return match ($this) {
            self::EMAILS => 'emails',
            self::DEFAULT => 'default',
        };
    }

    public static function list(): array
    {
        return [
            self::EMAILS,
            self::DEFAULT,
        ];
    }
}
