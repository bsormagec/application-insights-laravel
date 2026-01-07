<?php

namespace Sormagec\AppInsightsLaravel\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\Logger;

class LogMailSent
{
    public function __construct(
        protected AppInsightsServer $appInsights
    ) {
    }

    /**
     * Handle the MessageSent event.
     * Tracks sent emails in Application Insights.
     */
    public function handle(MessageSent $event): void
    {
        try {
            if (!Config::get('features.mail', true)) {
                return;
            }

            $this->appInsights->trackEvent('mail.sent', [
                'mail.to' => $this->getRecipients($event),
                'mail.subject' => $event->message->getSubject() ?? 'No subject',
                'mail.class' => $this->getNotificationClass($event),
            ]);

        } catch (\Throwable $e) {
            Logger::error('LogMailSent listener error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Get recipients as a string (limited for privacy)
     */
    private function getRecipients(MessageSent $event): string
    {
        try {
            $to = $event->message->getTo();
            if (is_array($to)) {
                $count = count($to);
                return $count . ' recipient(s)';
            }
            return '1 recipient';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Get the notification class name if available
     */
    private function getNotificationClass(MessageSent $event): string
    {
        try {
            $notification = $event->data['__laravel_notification'] ?? null;
            if (is_object($notification)) {
                return get_class($notification);
            }
            return 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }
}
