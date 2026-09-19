<?php

namespace Tests\Feature;

use App\Models\MonitoredMessage;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonitoredMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_analyzed_message(): void
    {
        config(['services.openai.key' => null]);
        Http::fake([
            'example.com/*' => Http::response('<html><body>Emergency delay and poor service report from Korle Bu.</body></html>'),
        ]);
        Storage::fake('public');

        $response = $this->post('/messages', [
            'source' => 'Patient Feedback',
            'author' => 'Ama',
            'source_url' => 'https://example.com/korle-bu-feedback',
            'content' => 'Patients are angry about emergency delay and poor service.',
            'attachments' => [
                UploadedFile::fake()->image('complaint-screenshot.jpg'),
                UploadedFile::fake()->create('incident-video.mp4', 500, 'video/mp4'),
            ],
        ]);

        $message = MonitoredMessage::first();

        $response->assertRedirect(route('messages.show', $message));
        $this->assertDatabaseHas('monitored_messages', [
            'source' => 'Patient Feedback',
            'author' => 'Ama',
            'source_url' => 'https://example.com/korle-bu-feedback',
            'sentiment' => 'negative',
            'crisis_level' => 'medium',
        ]);
        $this->assertNotEmpty($message->detailed_analysis);
        $this->assertStringContainsString('classified the record as medium', $message->detailed_analysis);
        $this->assertCount(2, $message->attachments);
        $this->assertSame('image', $message->attachments[0]['type']);
        $this->assertSame('video', $message->attachments[1]['type']);

        Storage::disk('public')->assertExists($message->attachments[0]['path']);
        Storage::disk('public')->assertExists($message->attachments[1]['path']);
    }

    public function test_source_link_only_payload_is_analyzed(): void
    {
        config(['services.openai.key' => null]);
        Http::fake([
            'example.com/*' => Http::response('<html><body>Patients complained about long waiting time and poor emergency communication.</body></html>'),
        ]);

        $response = $this->post('/messages', [
            'source' => 'News Comment',
            'source_url' => 'https://example.com/news/korle-bu',
        ]);

        $message = MonitoredMessage::first();

        $response->assertRedirect(route('messages.show', $message));
        $this->assertDatabaseHas('monitored_messages', [
            'source' => 'News Comment',
            'source_url' => 'https://example.com/news/korle-bu',
            'content' => null,
        ]);
        $this->assertStringContainsString('source link was fetched', $message->detailed_analysis);
    }

    public function test_missing_message_content_returns_validation_error(): void
    {
        $response = $this->from('/')
            ->post('/messages', [
                'source' => 'Manual Entry',
                'content' => '',
            ]);

        $response
            ->assertRedirect('/')
            ->assertSessionHasErrors('content');
    }

    public function test_evidence_only_payload_creates_message_without_text(): void
    {
        config(['services.openai.key' => null]);
        Storage::fake('public');

        $response = $this->post('/messages', [
            'source' => '',
            'content' => '',
            'attachments' => [
                UploadedFile::fake()->image('evidence.png'),
            ],
        ]);

        $message = MonitoredMessage::first();

        $response->assertRedirect(route('messages.show', $message));
        $this->assertDatabaseHas('monitored_messages', [
            'source' => 'Manual Entry',
            'content' => null,
            'sentiment' => 'neutral',
            'crisis_level' => 'low',
        ]);
        $this->assertStringContainsString('local fallback conclusion is limited', $message->detailed_analysis);
        $this->assertCount(1, $message->attachments);
        Storage::disk('public')->assertExists($message->attachments[0]['path']);
    }

    public function test_message_detail_page_renders_existing_record(): void
    {
        $message = MonitoredMessage::factory()->create([
            'content' => 'Emergency delays were reported at the outpatient department.',
        ]);

        $response = $this->get(route('messages.show', $message));

        $response
            ->assertOk()
            ->assertSee('Message Analysis')
            ->assertSee('Emergency delays were reported')
            ->assertSee('Detailed AI Findings');
    }
}
