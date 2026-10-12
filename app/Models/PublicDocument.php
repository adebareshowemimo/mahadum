<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property string $kind
 * @property string $title
 * @property string $description
 * @property string $heading
 * @property string|null $intro
 * @property string|null $body
 * @property string|null $author
 * @property bool $published
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 * @property array<string, mixed>|null $content
 */
class PublicDocument extends Model
{
    protected $guarded = [];

    protected $casts = ['content' => 'array', 'published' => 'boolean', 'published_at' => 'datetime'];
}
