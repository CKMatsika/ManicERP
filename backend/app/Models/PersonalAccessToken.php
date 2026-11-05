<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;
}
