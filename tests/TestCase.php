<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_keys(config('features', [])) as $key) {
            config(["features.{$key}.state" => 'enabled']);
        }
    }
}
