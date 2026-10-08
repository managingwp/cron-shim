<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LogLevel;
use App\Enums\RunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestReportRequest extends FormRequest
{
    /**
     * Authentication is handled by the AuthenticateSite middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_uuid' => ['required', 'uuid'],

            'run' => ['required', 'array'],
            'run.run_uuid' => ['required', 'uuid'],
            'run.started_at' => ['nullable', 'date'],
            'run.finished_at' => ['nullable', 'date'],
            'run.duration_ms' => ['nullable', 'integer', 'min:0'],
            'run.status' => ['required', Rule::enum(RunStatus::class)],
            'run.exit_code' => ['nullable', 'integer'],
            'run.jobs_run' => ['nullable', 'integer', 'min:0'],
            'run.jobs_failed' => ['nullable', 'integer', 'min:0'],
            'run.summary' => ['nullable', 'string', 'max:1000'],

            'logs' => ['nullable', 'array', 'max:'.(int) config('cronshim.ingest.max_log_entries')],
            'logs.*.level' => ['required', Rule::enum(LogLevel::class)],
            'logs.*.message' => ['required', 'string', 'max:'.(int) config('cronshim.ingest.max_log_message_length')],
            'logs.*.logged_at' => ['nullable', 'date'],
            'logs.*.context' => ['nullable', 'array'],

            'meta' => ['nullable', 'array'],
        ];
    }
}
