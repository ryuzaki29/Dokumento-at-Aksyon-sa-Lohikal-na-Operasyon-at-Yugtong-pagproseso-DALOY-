<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RouteActionType: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Forwarded = 'forwarded';
    case Returned = 'returned';
    case Approved = 'approved';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Forwarded => 'Forwarded',
            self::Returned => 'Returned',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::Forwarded => 'primary',
            self::Returned => 'danger',
            self::Approved => 'success',
            self::Completed => 'success',
        };
    }
}
