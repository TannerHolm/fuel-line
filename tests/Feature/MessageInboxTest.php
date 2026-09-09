<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MessageInboxTest extends TestCase
{
    use RefreshDatabase;

    private function founder(): User
    {
        return User::factory()->create(['role' => 'founder']);
    }

    private function accountWithMessages(string $name, array $messages): Account
    {
        $account = Account::create(['name' => $name, 'phone' => '3853501234']);

        foreach ($messages as $m) {
            $account->messages()->create($m + [
                'channel' => 'sms', 'from_address' => '+1', 'to_address' => '+2',
            ]);
        }

        return $account;
    }

    public function test_inbox_is_founder_only()
    {
        $this->get('/messages')->assertRedirect('/login');

        $retailer = User::factory()->create(['role' => 'retailer']);
        $this->actingAs($retailer)->get('/messages')->assertForbidden();
    }

    public function test_inbox_orders_by_latest_activity_with_unread_counts()
    {
        $quiet = $this->accountWithMessages('Quiet Stop', [
            ['direction' => 'out', 'status' => 'delivered', 'body' => 'old outbound', 'created_at' => now()->subDays(3)],
        ]);
        $busy = $this->accountWithMessages('Busy Stop', [
            ['direction' => 'in', 'status' => 'received', 'body' => 'need a restock', 'created_at' => now()->subHour()],
            ['direction' => 'in', 'status' => 'received', 'body' => 'you there?', 'created_at' => now()],
        ]);

        $this->actingAs($this->founder())->get('/messages')->assertInertia(
            fn (AssertableInertia $page) => $page->component('Messages/Index')
                ->has('conversations', 2)
                ->where('conversations.0.name', 'Busy Stop')
                ->where('conversations.0.unread_count', 2)
                ->where('conversations.1.name', 'Quiet Stop')
                ->where('conversations.1.unread_count', 0)
                ->where('unreadMessages', 2)
        );
    }

    public function test_mark_read_clears_unread_and_badge()
    {
        $account = $this->accountWithMessages('Read Stop', [
            ['direction' => 'in', 'status' => 'received', 'body' => 'hello'],
        ]);

        $founder = $this->founder();
        $this->actingAs($founder)->post("/accounts/{$account->id}/messages/read")->assertRedirect();

        $this->assertNotNull($account->messages()->sole()->read_at);

        $this->actingAs($founder)->get('/messages')->assertInertia(
            fn (AssertableInertia $page) => $page->where('unreadMessages', 0)
        );
    }

    public function test_unmatched_messages_are_listed()
    {
        \App\Models\Message::create([
            'channel' => 'sms', 'direction' => 'in', 'status' => 'received',
            'from_address' => '+12085551234', 'to_address' => '+1', 'body' => 'mystery text',
        ]);

        $this->actingAs($this->founder())->get('/messages')->assertInertia(
            fn (AssertableInertia $page) => $page->has('unmatched', 1)
                ->where('unmatched.0.from', '+12085551234')
        );
    }

    public function test_account_show_exposes_thread_and_message_timeline_rows()
    {
        $account = $this->accountWithMessages('Thread Stop', [
            ['direction' => 'out', 'status' => 'delivered', 'body' => 'First outreach message'],
        ]);

        $this->actingAs($this->founder())->get("/accounts/{$account->id}")->assertInertia(
            fn (AssertableInertia $page) => $page->component('Accounts/Show')
                ->has('thread', 1)
                ->where('thread.0.body', 'First outreach message')
                ->where('account.sms_opted_out', false)
                ->where('timeline', fn ($timeline) => collect($timeline)->contains(fn ($row) => $row['kind'] === 'message'))
        );
    }
}
