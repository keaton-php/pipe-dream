<?php

namespace Keaton\PipeDream\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A model keyed by a string (a UUID or ULID). */
class StringKeyWidget extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
