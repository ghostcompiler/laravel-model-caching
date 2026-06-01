<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Fixtures;

use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasModelCaching;

    protected $table = 'roles';
}
