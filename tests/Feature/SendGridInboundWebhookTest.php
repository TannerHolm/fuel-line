<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendGridInboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sendgrid.inbound_token' => 'secret-token']);
    }

    public function test_wrong_or_blank_token_is_rejected()
    {
        $this->post('/webhooks/sendgrid/inbound/nope', ['text' => 'hi'])->assertStatus(403);

        config(['services.sendgrid.inbound_token' => '']);
        $this->post('/webhooks/sendgrid/inbound/secret-token', ['text' => 'hi'])->assertStatus(403);

        $this->assertSame(0, Message::count());
    }

    public function test_inbound_email_matches_account_case_insensitively()
    {
        $account = Account::create(['name' => 'Mail Stop', 'email' => 'joe@shop.com']);

        $this->post('/webhooks/sendgrid/inbound/secret-token', [
            'from' => 'Joe Buyer <JOE@Shop.com>',
            'to' => 'reply@reply.test.example',
            'subject' => 'Re: Restock',
            'text' => "Sounds good, send 50.\n\nOn Tue, Fuel Line wrote:\n> Ready for more?",
            'headers' => "Received: by mx\nMessage-ID: <abc123@mail.shop.com>\nDate: today",
        ])->assertOk();

        $message = Message::sole();
        $this->assertSame($account->id, $message->account_id);
        $this->assertSame('email', $message->channel->value);
        $this->assertSame('joe@shop.com', $message->from_address);
        $this->assertSame('Re: Restock', $message->subject);
        $this->assertSame('<abc123@mail.shop.com>', $message->provider_message_id);
    }

    public function test_duplicate_message_id_is_stored_once()
    {
        Account::create(['name' => 'Dup Mail', 'email' => 'dup@shop.com']);
        $payload = [
            'from' => 'dup@shop.com', 'to' => 'reply@x', 'subject' => 'Hi', 'text' => 'Hello',
            'headers' => 'Message-ID: <dup-1@mail>',
        ];

        $this->post('/webhooks/sendgrid/inbound/secret-token', $payload)->assertOk();
        $this->post('/webhooks/sendgrid/inbound/secret-token', $payload)->assertOk();

        $this->assertSame(1, Message::count());
    }

    public function test_unmatched_sender_is_stored_with_null_account()
    {
        $this->post('/webhooks/sendgrid/inbound/secret-token', [
            'from' => 'stranger@nowhere.com', 'to' => 'reply@x', 'subject' => 'Hi', 'text' => 'Hello',
            'headers' => 'Message-ID: <str-1@mail>',
        ])->assertOk();

        $this->assertNull(Message::sole()->account_id);
    }

    public function test_html_only_email_falls_back_to_stripped_html()
    {
        Account::create(['name' => 'Html Mail', 'email' => 'html@shop.com']);

        $this->post('/webhooks/sendgrid/inbound/secret-token', [
            'from' => 'html@shop.com', 'to' => 'reply@x', 'subject' => 'Hi',
            'html' => '<p>Rich <strong>reply</strong></p>',
            'headers' => 'Message-ID: <html-1@mail>',
        ])->assertOk();

        $this->assertStringContainsString('Rich reply', Message::sole()->body);
    }
}
