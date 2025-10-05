<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    protected $table = 'nas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description'
    ];

    protected $casts = [
        'id' => 'integer',
        'ports' => 'integer'
    ];
}
