<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_control_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/cp');
    }
}
