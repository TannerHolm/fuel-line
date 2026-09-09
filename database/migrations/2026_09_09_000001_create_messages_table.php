<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete(); // null = inbound we couldn't match
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // founder who sent; null for inbound
            $table->string('channel', 10); // sms, email
            $table->string('direction', 5); // in, out
            $table->string('status', 15)->default('queued'); // queued, sent, delivered, failed, received
            $table->string('from_address'); // E.164 phone or email address
            $table->string('to_address');
            $table->string('subject')->nullable(); // email only
            $table->text('body');
            $table->string('provider_message_id')->nullable()->unique(); // Twilio SM sid / SendGrid message id — dedupe + status callbacks
            $table->text('error')->nullable();
            $table->timestamp('read_at')->nullable(); // inbound rows only
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['direction', 'read_at']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            // TCPA: when the buyer texted STOP. Blocks in-app SMS sends until START clears it.
            $table->timestamp('sms_opted_out_at')->nullable();
            // When the partner checked the SMS-consent box at signup (audit trail
            // for the A2P campaign's call-to-action).
            $table->timestamp('sms_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['sms_opted_out_at', 'sms_consent_at']);
        });

        Schema::dropIfExists('messages');
    }
};
