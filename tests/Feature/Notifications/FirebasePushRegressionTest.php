<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationPreference;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Regression for a real production bug: any notification whose via() can
 * resolve to the "firebase" channel (MethodsHelper::userNotificationPreference,
 * gated on notification_preferences.can_receive_push) MUST implement
 * toFirebase(), or FirebaseChannel::send() fatals with "Call to undefined
 * method ...::toFirebase()" — Notification::fake() never catches this
 * because it short-circuits before via()/toFirebase() ever run, so this has
 * to be tested without faking.
 */
class FirebasePushRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every notification class that can be dispatched over the "firebase"
     * channel (via MethodsHelper::userNotificationPreference, or a literal
     * 'firebase' in its own via()) must define toFirebase() itself or pull
     * it in via App\Notifications\Concerns\SendsFirebasePush.
     */
    public function test_every_push_capable_notification_implements_to_firebase(): void
    {
        $offenders = [];

        foreach ($this->notificationFiles() as $file) {
            $source = file_get_contents($file);

            $isPushCapable = str_contains($source, 'userNotificationPreference')
                || str_contains($source, "'firebase'")
                || str_contains($source, '"firebase"');

            if (!$isPushCapable) {
                continue;
            }

            $hasToFirebase = str_contains($source, 'function toFirebase')
                || str_contains($source, 'SendsFirebasePush');

            if (!$hasToFirebase) {
                $offenders[] = $file;
            }
        }

        $this->assertEmpty(
            $offenders,
            "These notifications can be routed through the firebase channel "
                . "but never define toFirebase():\n" . implode("\n", $offenders)
        );
    }

    /**
     * The concrete bug report: cancelling a session notifies the counterpart,
     * and a push-enabled user's real (non-faked) dispatch must not fatal.
     */
    public function test_session_cancelled_notification_delivers_to_a_push_enabled_user(): void
    {
        $user = User::factory()->create(['fcm_token' => 'test-token']);
        NotificationPreference::create([
            'user_id' => $user->id,
            'can_receive_push' => 1,
            'can_receive_mail' => 0,
        ]);

        $session = TherapySession::factory()->create(['user_id' => $user->id]);

        NotificationFacade::send($user, new SessionCancelledNotification($session, false));

        $this->assertTrue(true, 'Dispatch completed without a fatal error.');
    }

    /** @return string[] absolute paths of every *.php file under app/Notifications */
    private function notificationFiles(): array
    {
        $root = app_path('Notifications');
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }
}
