<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebPushSubscription extends Model
{
    protected $guarded = [];

    protected $hidden = ['endpoint', 'public_key', 'auth_key'];

    protected function casts(): array
    {
        return ['endpoint' => 'encrypted', 'public_key' => 'encrypted', 'auth_key' => 'encrypted'];
    }
}
