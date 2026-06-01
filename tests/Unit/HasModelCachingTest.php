<?php

namespace GhostCompiler\LaravelModelCaching\Tests\Unit;

use GhostCompiler\LaravelModelCaching\Tests\Fixtures\User;
use GhostCompiler\LaravelModelCaching\Tests\TestCase;

class HasModelCachingTest extends TestCase
{
    public function test_model_using_trait_can_boot_without_recursive_observer_registration(): void
    {
        $this->assertInstanceOf(User::class, new User);
    }
}
