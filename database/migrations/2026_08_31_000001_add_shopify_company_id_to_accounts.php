<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Shopify B2B Company id — the authoritative wholesale identity
            // when the store uses Companies (customers are the fallback).
            $table->string('shopify_company_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('shopify_company_id');
        });
    }
};
