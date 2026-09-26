<?php

namespace App\Models;

use Database\Factories\ToolPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The SEO copy around one calculator: intro, long body, FAQ, meta (spec §6).
 *
 * Keeping this in the database rather than in .astro files means the owner can
 * revise the copy without a deploy, and the Astro build can fail loudly if the
 * API is unreachable (spec §10.1).
 *
 * @property int $id
 * @property string $tool_key
 * @property string $title
 */
class ToolPage extends Model
{
    /** @use HasFactory<ToolPageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tool_key',
        'title',
        'intro_markdown',
        'body_markdown',
        'faq',
        'meta_title',
        'meta_description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'faq' => 'array',
        ];
    }
}
