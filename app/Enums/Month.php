<?php

namespace App\Enums;

enum Month: int
{
    case Enero = 1;
    case Febrero = 2;
    case Marzo = 3;
    case Abril = 4;
    case Mayo = 5;
    case Junio = 6;
    case Julio = 7;
    case Agosto = 8;
    case Septiembre = 9;
    case Octubre = 10;
    case Noviembre = 11;
    case Diciembre = 12;

    /**
     * The month as it appears in a dashboard address, such as jan.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Enero => 'jan',
            self::Febrero => 'feb',
            self::Marzo => 'mar',
            self::Abril => 'apr',
            self::Mayo => 'may',
            self::Junio => 'jun',
            self::Julio => 'jul',
            self::Agosto => 'aug',
            self::Septiembre => 'sep',
            self::Octubre => 'oct',
            self::Noviembre => 'nov',
            self::Diciembre => 'dec',
        };
    }

    /**
     * The short name shown on a month filter, such as Ene.
     */
    public function short(): string
    {
        return match ($this) {
            self::Enero => 'Ene',
            self::Febrero => 'Feb',
            self::Marzo => 'Mar',
            self::Abril => 'Abr',
            self::Mayo => 'May',
            self::Junio => 'Jun',
            self::Julio => 'Jul',
            self::Agosto => 'Ago',
            self::Septiembre => 'Sep',
            self::Octubre => 'Oct',
            self::Noviembre => 'Nov',
            self::Diciembre => 'Dic',
        };
    }

    /**
     * Find a month from its dashboard address.
     */
    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $month) {
            if ($month->slug() === $slug) {
                return $month;
            }
        }

        return null;
    }
}
