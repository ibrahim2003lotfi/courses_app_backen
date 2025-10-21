<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        // Test an actual API endpoint instead of root
        $response = $this->get('/api/test');
        $response->assertStatus(200);
    }
}