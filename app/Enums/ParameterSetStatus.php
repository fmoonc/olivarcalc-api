<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a parameter set (spec §5.1).
 *
 * Labels are in Spanish because the admin panel is the owner's interface, and
 * every visible string in the product is Spanish (spec §0.1).
 */
enum ParameterSetStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
            self::Archived => 'Archivado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
            self::Archived => 'gray',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
