<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Fixtures;

use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasModelCaching;

    protected $table = 'comments';
}
