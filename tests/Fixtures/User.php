<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Fixtures;

use GhostCompiler\LaravelModelCaching\Concerns\HasModelCaching;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasModelCaching;

    protected $table = 'users';

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
