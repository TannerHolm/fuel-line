<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.token' => 'test-token']);
    }

    /** Posts with a signature computed the way Twilio computes it. */
    private function signedPost(string $uri, array $params): TestResponse
    {
        ksort($params);
        $data = rtrim(config('app.url'), '/').$uri;

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $signature = base64_encode(hash_hmac('sha1', $data, 'test-token', true));

        return $this->post($uri, $params, ['X-Twilio-Signature' => $signature]);
    }

    public function test_unsigned_or_missigned_requests_are_rejected()
    {
        $this->post('/webhooks/twilio/inbound', ['Body' => 'Hi'])->assertStatus(401);

        $this->post('/webhooks/twilio/inbound', ['Body' => 'Hi'], ['X-Twilio-Signature' => 'bogus'])
            ->assertStatus(401);

        config(['services.twilio.token' => '']);
        $this->signedPost('/webhooks/twilio/inbound', ['Body' => 'Hi'])->assertStatus(401);

        $this->assertSame(0, Message::count());
    }

    public function test_inbound_sms_matches_account_by_fuzzy_phone()
    {
        $account = Account::create(['name' => 'Fuzzy Phone Stop', 'phone' => '(385) 350-1234']);

        $this->signedPost('/webhooks/twilio/inbound', [
            'MessageSid' => 'SMin1',
            'From' => '+13853501234',
            'To' => '+13853508287',
            'Body' => 'Yes, send 25 more',
        ])->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $message = Message::sole();
        $this->assertSame($account->id, $message->account_id);
        $this->assertSame('received', $message->status->value);
        $this->assertSame('in', $message->direction->value);
        $this->assertNull($message->read_at);
    }

    public function test_stop_records_opt_out_and_start_clears_it()
    {
        $account = Account::create(['name' => 'Stop Shop', 'phone' => '3853501234']);

        $this->signedPost('/webhooks/twilio/inbound', [
            'MessageSid' => 'SMstop', 'From' => '+13853501234', 'To' => '+1', 'Body' => 'STOP',
        ])->assertOk();

        $this->assertNotNull($account->fresh()->sms_opted_out_at);
        $this->assertSame(1, Message::count()); // the STOP itself is visible in the thread

        $this->signedPost('/webhooks/twilio/inbound', [
            'MessageSid' => 'SMstart', 'From' => '+13853501234', 'To' => '+1', 'Body' => 'start',
        ])->assertOk();

        $this->assertNull($account->fresh()->sms_opted_out_at);
    }

    public function test_duplicate_message_sid_is_stored_once()
    {
        Account::create(['name' => 'Dup Stop', 'phone' => '3853501234']);
        $payload = ['MessageSid' => 'SMdup', 'From' => '+13853501234', 'To' => '+1', 'Body' => 'hello'];

        $this->signedPost('/webhooks/twilio/inbound', $payload)->assertOk();
        $this->signedPost('/webhooks/twilio/inbound', $payload)->assertOk();

        $this->assertSame(1, Message::count());
    }

    public function test_unknown_sender_is_stored_unmatched()
    {
        $this->signedPost('/webhooks/twilio/inbound', [
            'MessageSid' => 'SMwho', 'From' => '+12085551234', 'To' => '+1', 'Body' => 'Who dis',
        ])->assertOk();

        $this->assertNull(Message::sole()->account_id);
    }

    public function test_status_callback_updates_without_regressing()
    {
        $account = Account::create(['name' => 'Status Stop', 'phone' => '3853501234']);
        $message = $account->messages()->create([
            'channel' => 'sms', 'direction' => 'out', 'status' => 'sent',
            'from_address' => '+1', 'to_address' => '+13853501234', 'body' => 'x',
            'provider_message_id' => 'SMout1',
        ]);

        $this->signedPost('/webhooks/twilio/status', ['MessageSid' => 'SMout1', 'MessageStatus' => 'delivered'])
            ->assertOk();
        $this->assertSame('delivered', $message->fresh()->status->value);

        // A late "sent" must not regress the delivered status.
        $this->signedPost('/webhooks/twilio/status', ['MessageSid' => 'SMout1', 'MessageStatus' => 'sent'])
            ->assertOk();
        $this->assertSame('delivered', $message->fresh()->status->value);

        $failed = $account->messages()->create([
            'channel' => 'sms', 'direction' => 'out', 'status' => 'sent',
            'from_address' => '+1', 'to_address' => '+13853501234', 'body' => 'y',
            'provider_message_id' => 'SMout2',
        ]);

        $this->signedPost('/webhooks/twilio/status', [
            'MessageSid' => 'SMout2', 'MessageStatus' => 'undelivered', 'ErrorCode' => '30034',
        ])->assertOk();

        $failed = $failed->fresh();
        $this->assertSame('failed', $failed->status->value);
        $this->assertSame('Twilio error 30034', $failed->error);

        // Unknown sid is acknowledged, not an error.
        $this->signedPost('/webhooks/twilio/status', ['MessageSid' => 'SMnope', 'MessageStatus' => 'delivered'])
            ->assertOk();
    }
}
