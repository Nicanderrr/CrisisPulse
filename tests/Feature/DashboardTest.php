<?php

namespace Tests\Feature;

use App\Models\MonitoredMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_renders_crisispulse_summary(): void
    {
        MonitoredMessage::factory()->create([
            'sentiment' => 'negative',
            'crisis_level' => 'high',
            'content' => 'There is a serious emergency delay at the hospital.',
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('CrisisPulse AI')
            ->assertSee('Crisis Communication Dashboard')
            ->assertSee('High Crisis Alerts');
    }
}
