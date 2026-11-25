<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ErrorAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_goes_to_the_webhook_once_an_hour(): void
    {
        Setting::put(['error_alerts_enabled' => '1', 'error_alert_webhook' => 'https://hooks.example.com/abc']);
        Http::fake(['hooks.example.com/*' => Http::response('ok')]);
        $e = new RuntimeException('Payment gateway timed out');

        $this->assertTrue(ErrorAlert::send($e));
        $this->assertFalse(ErrorAlert::send($e)); // same error within the hour
        $this->assertTrue(ErrorAlert::send(new RuntimeException('Other error'))); // a different one is sent

        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'RuntimeException: Payment gateway timed out') && $r['content'] !== '');
    }

    public function test_nothing_is_sent_while_testing_or_when_off(): void
    {
        Http::fake();
        ErrorAlert::report(new RuntimeException('x')); // tests never alert
        Setting::put(['error_alerts_enabled' => '0', 'error_alert_webhook' => 'https://hooks.example.com/abc']);
        $this->assertFalse(ErrorAlert::enabled());
        Http::assertNothingSent();
    }

    public function test_settings_test_button(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'role' => 'owner']);
        $this->actingAs($owner)->postJson('/admin/settings/test-error-alert')->assertStatus(422);
        Setting::put(['error_alerts_enabled' => '1', 'error_alert_webhook' => 'https://hooks.example.com/abc']);
        Http::fake(['hooks.example.com/*' => Http::response('ok')]);
        $this->actingAs($owner)->postJson('/admin/settings/test-error-alert')->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(fn ($r) => str_starts_with($r['text'], '[Test] Error on'));
    }
}
