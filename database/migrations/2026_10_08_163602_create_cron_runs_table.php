<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('run_uuid');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status')->default('success');
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('jobs_run')->default(0);
            $table->unsignedInteger('jobs_failed')->default(0);
            $table->string('summary')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'run_uuid']);
            $table->index(['site_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_runs');
    }
};
