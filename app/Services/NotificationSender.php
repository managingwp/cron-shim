<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class NotificationSender
{
    /**
     * Deliver a message over a configured channel.
     */
    public function send(NotificationChannel $channel, string $subject, string $body): void
    {
        match ($channel->type) {
            NotificationChannelType::Email => $this->sendEmail($channel, $subject, $body),
            NotificationChannelType::Slack => $this->sendSlack($channel, $subject, $body),
        };
    }

    private function sendEmail(NotificationChannel $channel, string $subject, string $body): void
    {
        $recipients = array_values(array_filter((array) ($channel->config['recipients'] ?? [])));

        if ($recipients === []) {
            throw new RuntimeException('No email recipients configured.');
        }

        Mail::raw($body, function ($message) use ($recipients, $subject): void {
            $message->to($recipients)->subject($subject);
        });
    }

    private function sendSlack(NotificationChannel $channel, string $subject, string $body): void
    {
        $url = (string) ($channel->config['webhook_url'] ?? '');

        if ($url === '') {
            throw new RuntimeException('No Slack webhook configured.');
        }

        Http::timeout(10)->post($url, ['text' => "*{$subject}*\n{$body}"])->throw();
    }
}
