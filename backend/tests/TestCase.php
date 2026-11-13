<?php

namespace Tests;

use App\Support\Rights;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Rights::clearCache();

        parent::tearDown();
    }
}
