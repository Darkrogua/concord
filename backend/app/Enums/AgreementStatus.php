<?php

namespace App\Enums;

enum AgreementStatus: string
{
    case Draft = 'draft';
    case Awaiting = 'awaiting';
    case Completed = 'completed';
    case Expired = 'expired';
    case Archived = 'archived';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Awaiting => 'Ждет согласования',
            self::Completed => 'Завершено',
            self::Expired => 'Просрочено',
            self::Archived => 'Архив',
            self::Deleted => 'Корзина',
        };
    }
}
