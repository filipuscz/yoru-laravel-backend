<?php

namespace App\Services;

use App\Jobs\SendFirebaseNotification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    public function __construct(private Messaging $messaging) {}

    /**
     * @param  list<string>  $deviceTokens
     * @param  array<string, string>  $data
     */
    public function queue(
        array $deviceTokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
    ): void {
        $deviceTokens = array_values(array_unique(array_filter(
            $deviceTokens,
            fn (string $token): bool => $token !== '',
        )));

        if ($deviceTokens === []) {
            return;
        }

        SendFirebaseNotification::dispatch(
            $deviceTokens,
            $title,
            $body,
            $data,
            $imageUrl,
        )->onQueue('notifications');
    }

    /**
     * @param  list<string>  $deviceTokens
     * @param  array<string, string>  $data
     */
    public function sendNow(
        array $deviceTokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
    ): void {
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body, $imageUrl))
            ->withData($data);

        $this->messaging->sendMulticast($message, $deviceTokens);
    }
}
