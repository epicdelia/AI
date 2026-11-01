<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\ClientBrandVault;
use App\Models\Deal;
use App\Models\User;
use App\Services\Prompts;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Enterprise Slack, Microsoft Teams, and Webhook Dispatcher.
 * Dispatches real-time Slack Block Kit notifications to brand channels
 * for review alerts, revision comments, and live milestones.
 */
class ClientWebhookDispatcher
{
    public static function dispatch(Deal $deal, string $eventType, string $title, string $message, ?string $actionUrl = null): bool
    {
        if (! $deal->user_id) {
            return false;
        }

        $ownerId = $deal->client?->teamOwnerId() ?? $deal->user_id;
        $vault = ClientBrandVault::where('user_id', $ownerId)->first();
        $webhookUrl = $vault?->slack_webhook_url;

        if (! $webhookUrl || ! filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $creator = Prompts::name();
        $actionUrl ??= url('/requests/'.$deal->id);

        $payload = [
            'text' => "[{$creator} Studio] {$title}",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "✨ {$title}",
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Project:*\n{$deal->brand_name}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n".strtoupper($deal->stage),
                        ],
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Open in Studio',
                                'emoji' => true,
                            ],
                            'url' => $actionUrl,
                            'style' => 'primary',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(5)->post($webhookUrl, $payload);
            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public static function testPing(string $webhookUrl, User $user): array
    {
        if (! filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'Please enter a valid webhook URL.'];
        }

        $creator = Prompts::name();
        $payload = [
            'text' => "✨ {$creator} Studio: Webhook Verified",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '✨ Studio Webhook Connection Verified',
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "Hello *{$user->name}*! Your team Slack webhook is successfully connected to the *{$creator} Creator Studio*.\n\nYou'll receive real-time notifications here when new video review cuts are ready, revision feedback is posted, and campaigns go live.",
                    ],
                ],
            ],
        ];

        try {
            $res = Http::timeout(5)->post($webhookUrl, $payload);
            if ($res->successful()) {
                return ['ok' => true, 'message' => 'Test message sent to Slack successfully!'];
            }
            return ['ok' => false, 'message' => "Slack returned HTTP {$res->status()}: {$res->body()}"];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => "Connection failed: {$e->getMessage()}"];
        }
    }
}
