<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('founder')->index();
            $table->string('phone', 30)->nullable();
        });

        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('min_qty');
            $table->unsignedInteger('max_qty')->nullable(); // null = open-ended (250+)
            $table->decimal('unit_price', 8, 2)->nullable(); // null = quoted individually
            $table->boolean('self_serve')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->string('market');
            $table->string('community')->nullable();
            $table->date('date');
            $table->decimal('cost', 10, 2)->nullable();
            $table->unsignedInteger('pucks_sampled')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('retailer_type', 20)->nullable();   // service | performance | convenience
            $table->string('decision_maker')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('service_community_link')->nullable();
            $table->string('lead_source', 20)->nullable();
            $table->string('acquisition_engine', 10)->default('direct'); // direct | seeded
            $table->string('pipeline_stage', 30)->default('qualified_prospect')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_action')->nullable();
            $table->date('next_action_date')->nullable()->index();
            $table->string('lost_reason')->nullable();
            $table->string('shopify_customer_id')->nullable();
            $table->string('signup_source', 20)->default('founder'); // founder | self_service
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['account_id', 'to_stage']);
        });

        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('pucks_given')->default(0);
            $table->string('given_to', 20)->default('staff'); // staff | decision_maker
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // opening | reorder
            $table->date('date');
            $table->unsignedInteger('quantity');
            $table->string('tier', 30)->nullable(); // pricing_tiers.key at time of order
            $table->decimal('unit_price', 8, 2)->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('freight', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->string('shopify_draft_order_id')->nullable();
            $table->string('payment_status', 15)->default('pending'); // pending | invoiced | paid
            $table->date('fulfilled_at')->nullable(); // starts the sell-through clock
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'type']);
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('cadence', 10)->default('adhoc'); // 7 | 14 | 30 | adhoc
            $table->unsignedInteger('units_sold')->nullable();   // since last check-in
            $table->unsignedInteger('units_on_hand')->nullable();
            $table->json('flavors_moving')->nullable();
            $table->boolean('staff_recommends')->nullable();
            $table->text('buyer_profile')->nullable();
            $table->text('objections')->nullable();
            $table->boolean('ready_for_reorder')->default(false);
            $table->string('source', 10)->default('founder'); // founder | retailer
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'date']);
        });

        Schema::create('buyback_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agreement_version', 64); // hash/version of the terms shown at signing
            $table->string('signer_name');
            $table->string('signer_title')->nullable();
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('acquisition_story')->nullable();
            $table->text('opening_order_summary')->nullable();
            $table->text('sell_through_summary')->nullable();
            $table->text('reorder_summary')->nullable();
            $table->text('customer_insight')->nullable();
            $table->text('why_it_worked')->nullable();
            $table->text('quote')->nullable();
            $table->boolean('quote_authorized')->default(false);
            $table->string('status', 15)->default('draft'); // draft | published
            $table->timestamps();
        });

        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('week_of');
            $table->string('segment_key', 60)->default('all'); // all | type:service | engine:seeded | state:UT ...
            $table->unsignedInteger('qualified_conversations')->default(0);
            $table->decimal('opening_conversion_pct', 5, 1)->nullable();
            $table->decimal('avg_opening_order', 10, 2)->nullable();
            $table->decimal('units_per_store_week', 8, 2)->nullable();
            $table->decimal('avg_days_to_reorder', 6, 1)->nullable();
            $table->decimal('reorder_rate_pct', 5, 1)->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['week_of', 'segment_key']);
        });

        Schema::create('account_activation', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activation_id')->constrained()->cascadeOnDelete();
            $table->primary(['account_id', 'activation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activation');
        Schema::dropIfExists('kpi_snapshots');
        Schema::dropIfExists('case_studies');
        Schema::dropIfExists('buyback_agreements');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('stage_transitions');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('activations');
        Schema::dropIfExists('pricing_tiers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone']);
        });
    }
};
