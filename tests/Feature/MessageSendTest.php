<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.sid' => 'ACtest',
            'services.twilio.token' => 'test-token',
            'services.twilio.messaging_service_sid' => 'MGtest',
            'services.sendgrid.key' => 'SG.test',
            'services.sendgrid.from_address' => 'sales@test.example',
            'services.sendgrid.reply_domain' => 'reply.test.example',
        ]);
    }

    private function founder(): User
    {
        return User::factory()->create(['role' => 'founder']);
    }

    public function test_sms_send_creates_row_and_calls_twilio()
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);
        $account = Account::create(['name' => 'Texted Stop', 'phone' => '(385) 350-1234']);

        $this->actingAs($this->founder())
            ->post("/accounts/{$account->id}/messages", ['channel' => 'sms', 'body' => 'Shelf check next week?'])
            ->assertRedirect();

        $message = Message::sole();
        $this->assertSame('sent', $message->status->value);
        $this->assertSame('SM123', $message->provider_message_id);
        $this->assertSame('+13853501234', $message->to_address);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com')
            && $request['To'] === '+13853501234'
            && $request['MessagingServiceSid'] === 'MGtest'
            && str_contains($request['StatusCallback'], '/webhooks/twilio/status'));
    }

    public function test_failed_twilio_send_marks_row_failed_without_500()
    {
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Unreachable'], 400)]);
        $account = Account::create(['name' => 'Bad Number', 'phone' => '3853501234']);

        $this->actingAs($this->founder())
            ->post("/accounts/{$account->id}/messages", ['channel' => 'sms', 'body' => 'Hello'])
            ->assertRedirect();

        $message = Message::sole();
        $this->assertSame('failed', $message->status->value);
        $this->assertSame('Unreachable', $message->error);
    }

    public function test_email_send_calls_sendgrid_with_reply_to()
    {
        Http::fake(['api.sendgrid.com/*' => Http::response('', 202, ['X-Message-Id' => 'sg-abc'])]);
        $account = Account::create(['name' => 'Mail Stop', 'email' => 'buyer@mailstop.example']);

        $this->actingAs($this->founder())
            ->post("/accounts/{$account->id}/messages", ['channel' => 'email', 'subject' => 'Restock', 'body' => 'Ready for more?'])
            ->assertRedirect();

        $message = Message::sole();
        $this->assertSame('sent', $message->status->value);
        $this->assertSame('sg-abc', $message->provider_message_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.sendgrid.com')
            && $request['reply_to']['email'] === 'reply@reply.test.example'
            && $request['subject'] === 'Restock');
    }

    public function test_guards_reject_unusable_channels()
    {
        Http::fake();
        $founder = $this->founder();

        $noPhone = Account::create(['name' => 'No Phone']);
        $this->actingAs($founder)
            ->post("/accounts/{$noPhone->id}/messages", ['channel' => 'sms', 'body' => 'Hi'])
            ->assertSessionHasErrors('body');

        $optedOut = Account::create(['name' => 'Opted Out', 'phone' => '3853501234', 'sms_opted_out_at' => now()]);
        $this->actingAs($founder)
            ->post("/accounts/{$optedOut->id}/messages", ['channel' => 'sms', 'body' => 'Hi'])
            ->assertSessionHasErrors('body');

        $noEmail = Account::create(['name' => 'No Email']);
        $this->actingAs($founder)
            ->post("/accounts/{$noEmail->id}/messages", ['channel' => 'email', 'body' => 'Hi'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
        Http::assertNothingSent();
    }

    public function test_retailers_cannot_send()
    {
        $account = Account::create(['name' => 'Somewhere', 'phone' => '3853501234']);
        $retailer = User::factory()->create(['role' => 'retailer', 'account_id' => $account->id]);

        $this->actingAs($retailer)
            ->post("/accounts/{$account->id}/messages", ['channel' => 'sms', 'body' => 'Hi'])
            ->assertForbidden();
    }
}
