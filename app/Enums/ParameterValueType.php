<?php

namespace App\Enums;

enum ParameterValueType: string
{
    case Decimal = 'decimal';
    case Bool = 'bool';
    case String = 'string';
    case Json = 'json';
}
