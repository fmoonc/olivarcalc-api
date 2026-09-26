<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ParameterValueType: string implements HasLabel
{
    case Decimal = 'decimal';
    case Bool = 'bool';
    case String = 'string';
    case Json = 'json';

    public function getLabel(): string
    {
        return match ($this) {
            self::Decimal => 'Decimal',
            self::Bool => 'Sí / No',
            self::String => 'Texto',
            self::Json => 'JSON',
        };
    }
}
