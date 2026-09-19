<?php

namespace Tests\Feature;

use App\Models\MonitoredMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assistant_boot_returns_system_context(): void
    {
        MonitoredMessage::factory()->create([
            'sentiment' => 'negative',
            'crisis_level' => 'high',
        ]);

        $response = $this->getJson('/assistant/boot');

        $response
            ->assertOk()
            ->assertJsonPath('context.system.name', 'CrisisPulse AI')
            ->assertJsonPath('context.dashboard.total_messages', 1);
    }

    public function test_assistant_dashboard_summary_action_works(): void
    {
        MonitoredMessage::factory()->create([
            'sentiment' => 'negative',
            'crisis_level' => 'high',
        ]);

        $response = $this->postJson('/assistant/action', [
            'tool' => 'dashboard_summary',
            'arguments' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('total_messages', 1)
            ->assertJsonPath('negative', 1)
            ->assertJsonPath('high_risk', 1);
    }

    public function test_assistant_can_create_text_analysis_record(): void
    {
        config(['services.openai.key' => null]);

        $response = $this->postJson('/assistant/action', [
            'tool' => 'analyze_text',
            'arguments' => [
                'content' => 'Patients are angry about emergency delay and poor service.',
                'source' => 'Assistant Entry',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('sentiment', 'negative');

        $this->assertDatabaseHas('monitored_messages', [
            'source' => 'Assistant Entry',
            'sentiment' => 'negative',
        ]);
    }

    public function test_assistant_message_has_builtin_fallback_when_openai_is_unavailable(): void
    {
        config(['services.openai.key' => null]);

        $response = $this->postJson('/assistant/message', [
            'message' => 'Explain the system to me',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('offline', true)
            ->assertJsonFragment([
                'reply' => 'CrisisPulse AI monitors hospital-related communication, analyzes sentiment and crisis risk, stores evidence, and helps communication officers understand what needs attention.',
            ]);
    }
}
