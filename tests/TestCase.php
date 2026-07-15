<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Red de seguridad: ninguna petición HTTP real en tests. Los tests que
        // necesiten red deben usar Http::fake(); cualquier otra petición falla.
        Http::preventStrayRequests();
    }
}
