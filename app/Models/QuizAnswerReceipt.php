<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $fingerprint
 * @property array<string, mixed> $response
 */
class QuizAnswerReceipt extends Model
{
    protected $guarded = [];

    protected $casts = ['response' => 'array'];
}
