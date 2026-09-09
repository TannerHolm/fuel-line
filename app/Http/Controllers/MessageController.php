<?php

namespace App\Http\Controllers;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Account;
use App\Models\Message;
use App\Services\SendGridService;
use App\Services\TwilioService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /** Inbox: one row per account with message history, newest activity first. */
    public function index(): Response
    {
        $aggregates = Message::query()
            ->whereNotNull('account_id')
            ->selectRaw("account_id, MAX(created_at) AS last_message_at,
                SUM(CASE WHEN direction = 'in' AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_count")
            ->groupBy('account_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->get();

        $accounts = Account::whereIn('id', $aggregates->pluck('account_id'))
            ->get(['id', 'name', 'city', 'state', 'pipeline_stage'])
            ->keyBy('id');

        // Latest message per account for the preview line, resolved in PHP.
        $latest = Message::whereIn('account_id', $aggregates->pluck('account_id'))
            ->orderByDesc('created_at')
            ->get(['account_id', 'channel', 'direction', 'body', 'subject'])
            ->groupBy('account_id')
            ->map(fn ($messages) => $messages->first());

        $conversations = $aggregates
            ->filter(fn ($row) => $accounts->has($row->account_id))
            ->map(function ($row) use ($accounts, $latest) {
                $account = $accounts[$row->account_id];
                $last = $latest[$row->account_id] ?? null;

                return [
                    'account_id' => $row->account_id,
                    'name' => $account->name,
                    'location' => collect([$account->city, $account->state])->filter()->implode(', '),
                    'stage_label' => $account->pipeline_stage->label(),
                    'channel' => $last?->channel->label(),
                    'outbound' => $last?->direction === MessageDirection::Out,
                    'preview' => Str::limit(trim($last?->subject ?: (string) $last?->body), 90),
                    'last_message_at' => $row->last_message_at,
                    'unread_count' => (int) $row->unread_count,
                ];
            })
            ->values();

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'unmatched' => Message::whereNull('account_id')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Message $m) => [
                    'id' => $m->id,
                    'channel' => $m->channel->label(),
                    'from' => $m->from_address,
                    'preview' => Str::limit(trim($m->subject ?: $m->body), 90),
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => [
                'required', 'string',
                $request->input('channel') === MessageChannel::Sms->value ? 'max:1600' : 'max:10000',
            ],
        ]);

        $channel = MessageChannel::from($data['channel']);

        if ($channel === MessageChannel::Sms) {
            $to = Phone::toE164($account->phone);

            if ($to === null) {
                throw ValidationException::withMessages(['body' => 'This account has no usable phone number — add one on the account first.']);
            }

            if ($account->smsOptedOut()) {
                throw ValidationException::withMessages(['body' => 'This account opted out of SMS (replied STOP). They can text START to opt back in.']);
            }

            $from = config('services.twilio.from');
        } else {
            $to = $account->email;

            if (blank($to)) {
                throw ValidationException::withMessages(['body' => 'This account has no email address — add one on the account first.']);
            }

            $from = config('services.sendgrid.from_address');
        }

        $message = $account->messages()->create([
            'user_id' => $request->user()->id,
            'channel' => $channel,
            'direction' => MessageDirection::Out,
            'status' => MessageStatus::Queued,
            'from_address' => $from,
            'to_address' => $to,
            'subject' => $channel === MessageChannel::Email ? ($data['subject'] ?: null) : null,
            'body' => $data['body'],
        ]);

        dispatch(function () use ($message) {
            $message->channel === MessageChannel::Sms
                ? app(TwilioService::class)->sendSms($message)
                : app(SendGridService::class)->sendEmail($message);
        })->afterResponse();

        return back();
    }

    public function markRead(Account $account): RedirectResponse
    {
        // Read-tracking metadata, not content — the one sanctioned update
        // besides provider status flips.
        $account->messages()
            ->where('direction', MessageDirection::In->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
