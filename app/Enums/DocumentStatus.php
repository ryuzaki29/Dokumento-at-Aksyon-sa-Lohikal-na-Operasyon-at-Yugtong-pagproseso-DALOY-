<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    case Registered = 'registered';
    case InRouting = 'in_routing';
    case ForApproval = 'for_approval';
    case Returned = 'returned';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::InRouting => 'In Routing',
            self::ForApproval => 'For Approval',
            self::Returned => 'Returned',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registered => 'gray',
            self::InRouting => 'info',
            self::ForApproval => 'warning',
            self::Returned => 'danger',
            self::Completed => 'success',
        };
    }
}
