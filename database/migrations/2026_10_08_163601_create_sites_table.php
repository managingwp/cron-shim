<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('environment')->default('production');
            $table->string('timezone')->default('UTC');
            $table->unsignedInteger('expected_interval_minutes')->default(5);
            $table->unsignedInteger('grace_minutes')->default(10);
            $table->boolean('is_active')->default(true);
            $table->string('ingest_token_hash');
            $table->text('signing_secret');
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
