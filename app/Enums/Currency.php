<?php

namespace App\Enums;

enum Currency: string
{
    case Pen = 'pen';
    case Usd = 'usd';

    /**
     * The column heading used in the expenses table.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pen => 'Soles',
            self::Usd => 'Dólares',
        };
    }
}
