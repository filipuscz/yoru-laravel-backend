<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFirebaseNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * @param  list<string>  $deviceTokens
     * @param  array<string, string>  $data
     */
    public function __construct(
        public array $deviceTokens,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $imageUrl = null,
    ) {}

    public function handle(FirebaseNotificationService $service): void
    {
        $service->sendNow(
            $this->deviceTokens,
            $this->title,
            $this->body,
            $this->data,
            $this->imageUrl,
        );
    }
}
