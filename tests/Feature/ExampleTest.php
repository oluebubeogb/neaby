<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // `/` is the For You feed itself (same page as /home), not a redirect.
        $response->assertStatus(200);
        $response->assertSee('&quot;component&quot;:&quot;Home&quot;', false);
        $this->get('/home')->assertStatus(200)->assertSee('&quot;component&quot;:&quot;Home&quot;', false);
    }
}
