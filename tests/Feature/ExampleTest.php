<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_route_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
