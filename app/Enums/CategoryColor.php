<?php

namespace App\Enums;

enum CategoryColor: string
{
    case Green = 'green';
    case Purple = 'purple';
    case Blue = 'blue';
    case Gold = 'gold';
    case Red = 'red';

    /**
     * The name shown next to the color when a category is created.
     */
    public function label(): string
    {
        return match ($this) {
            self::Green => 'Green',
            self::Purple => 'Purple',
            self::Blue => 'Blue',
            self::Gold => 'Gold',
            self::Red => 'Red',
        };
    }

    /**
     * The color already used for a catalog category name.
     */
    public static function forCatalogName(string $name): ?self
    {
        return match ($name) {
            'Comida' => self::Green,
            'Otros' => self::Purple,
            'Vivienda' => self::Blue,
            'Financieros' => self::Gold,
            'PKYTP' => self::Red,
            default => null,
        };
    }
}
