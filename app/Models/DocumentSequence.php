<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToTenant;
    protected $fillable = ['school_id', 'type', 'year', 'last_number'];
}
