<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Fixtures;

use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasModelCaching;

    protected $table = 'posts';

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
