<?php

namespace Tests\Feature;

use App\Jobs\SendFirebaseNotification;
use App\Services\BaseService;
use App\Services\FirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Contract\Messaging;
use Mockery;
use Tests\TestCase;

class FirebaseNotificationServiceTest extends TestCase
{
    public function test_firebase_delivery_is_dispatched_to_a_focused_queued_job(): void
    {
        $this->assertTrue(class_exists(FirebaseNotificationService::class));
        $this->assertTrue(class_exists(SendFirebaseNotification::class));
        $this->assertTrue(is_subclass_of(SendFirebaseNotification::class, ShouldQueue::class));
        $this->assertFalse(method_exists(BaseService::class, 'firebaseNotification'));

        Queue::fake();
        $service = new FirebaseNotificationService(Mockery::mock(Messaging::class));
        $service->queue(
            ['token-a', 'token-a', 'token-b'],
            'Title',
            'Body',
            ['screen' => 'orders'],
        );

        Queue::assertPushed(SendFirebaseNotification::class, function (SendFirebaseNotification $job): bool {
            return $job->deviceTokens === ['token-a', 'token-b']
                && $job->title === 'Title'
                && $job->body === 'Body'
                && $job->data === ['screen' => 'orders'];
        });
    }
}
