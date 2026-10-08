<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Services\NotificationSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class NotificationChannelController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'channels' => NotificationChannel::query()->latest()->get(),
            'logs' => NotificationLog::query()->with('channel:id,name')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(NotificationChannelType::class)],
            'recipients' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url'],
        ]);

        if ($data['type'] === NotificationChannelType::Email->value) {
            $recipients = $this->lines($data['recipients'] ?? '');

            if ($recipients === []) {
                return back()->withErrors(['recipients' => 'Add at least one email address.'])->withInput();
            }

            $config = ['recipients' => $recipients];
        } else {
            if (($data['webhook_url'] ?? '') === '') {
                return back()->withErrors(['webhook_url' => 'A Slack webhook URL is required.'])->withInput();
            }

            $config = ['webhook_url' => $data['webhook_url']];
        }

        NotificationChannel::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'config' => $config,
            'is_active' => true,
        ]);

        return back()->with('status', 'Notification channel added.');
    }

    public function destroy(NotificationChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('status', 'Notification channel removed.');
    }

    public function test(NotificationChannel $channel, NotificationSender $sender): RedirectResponse
    {
        try {
            $sender->send($channel, '[Cron Shim] Test notification', 'This is a test notification from your cron-shim hub.');

            NotificationLog::query()->create([
                'notification_channel_id' => $channel->id,
                'subject' => 'Test notification',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return back()->with('status', 'Test notification sent to '.$channel->name.'.');
        } catch (Throwable $exception) {
            NotificationLog::query()->create([
                'notification_channel_id' => $channel->id,
                'subject' => 'Test notification',
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'sent_at' => now(),
            ]);

            return back()->withErrors(['test' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $input): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $input) ?: [])));
    }
}
