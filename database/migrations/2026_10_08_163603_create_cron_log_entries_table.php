<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_log_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cron_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'logged_at']);
            $table->index(['level', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_log_entries');
    }
};
