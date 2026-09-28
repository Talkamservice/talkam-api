<?php

namespace App\Services\Therapist;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\SessionNote;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Media\FileService;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\V2\MessageActionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionNoteService
{
    /**
     * The session, only for its assigned therapist.
     */
    public static function sessionForTherapist(User $user, $session_id): TherapySession
    {
        $session = TherapySession::with('therapist')->find($session_id);

        if (empty($session) || $session->therapist?->user_id != $user->id) {
            throw new ModelNotFoundException("Session not found");
        }

        return $session;
    }

    /**
     * Write or update the (unique-per-session) note. Private by default.
     */
    public function write(User $user, $session_id, array $data): SessionNote
    {
        $session = self::sessionForTherapist($user, $session_id);

        $validator = Validator::make($data, [
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'shared_with_client' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(['draft', 'final'])],
            'tags' => 'nullable|array',
            'tags.*' => [
                Rule::exists('post_categories', 'id')
                    ->where('type', PostCategoryConstants::TYPE_INTEREST_TOPIC),
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Captured before the upsert so sharing only fires the chat delivery
        // on the false→true transition — re-saving an already-shared note
        // (e.g. a wording fix) must not re-send it as a new chat message.
        $was_shared = SessionNote::where('session_id', $session->id)->value('shared_with_client');

        $note = SessionNote::updateOrCreate([
            'session_id' => $session->id,
        ], [
            'therapist_id' => $session->therapist_id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'shared_with_client' => $validated['shared_with_client'] ?? false,
            'status' => $validated['status'] ?? 'final',
            'tags' => $validated['tags'] ?? null,
        ]);

        if ($note->shared_with_client && !$was_shared) {
            self::deliverToChat($user, $session, $note);
        }

        return $note;
    }

    /**
     * Drops a shared note into the therapist↔client conversation as a file
     * attachment, reusing the same conversation-resolution the "Message"
     * button on a booking already uses (SessionLifecycleService::
     * startConversation) — safe to call from here because write() only
     * ever runs inside the therapist's own authenticated request, so
     * auth()->user() (which ConversationService::create() reads) is
     * already this same $therapist. Delivery is best-effort: a messaging
     * failure must never undo a clinical note that was already saved.
     */
    private static function deliverToChat(User $therapist, TherapySession $session, SessionNote $note): void
    {
        try {
            $conversation = (new ConversationService)->create([
                'receiver_id' => $session->user_id,
            ]);

            if ($conversation->status === StatusConstants::AWAITING_RESPONSE) {
                $conversation->update(['status' => StatusConstants::ACTIVE]);
            }

            $body = trim(sprintf(
                "%s\n\n%s\n\n— Shared by %s on %s",
                $note->title,
                $note->content ?: '(No additional details)',
                $therapist->full_name,
                now()->format('M j, Y')
            ));

            $dir = storage_path('app/tmp');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $tmp_path = $dir . '/' . uniqid('session-note-') . '.txt';
            file_put_contents($tmp_path, $body);

            $file = (new FileService)
                ->setFilename(Str::slug($note->title) . '.txt')
                ->save($tmp_path, 'session-notes', null, $therapist->id);

            (new MessageActionService)->sendFile(
                $therapist,
                $conversation->id,
                $file->id,
                "Shared their session notes: {$note->title}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to deliver shared session note to chat', [
                'session_id' => $session->id,
                'note_id' => $note->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function view(User $user, $session_id): ?SessionNote
    {
        $session = self::sessionForTherapist($user, $session_id);

        return SessionNote::where('session_id', $session->id)->first();
    }

    /**
     * Notes library (§12): the authed therapist's notes, filterable by
     * client and searchable over title/body.
     */
    public static function library(User $user, array $filters = [])
    {
        $therapist = $user->therapist;

        $builder = SessionNote::with('session')
            ->where('therapist_id', $therapist->id);

        if (!empty($client_id = $filters['client_id'] ?? null)) {
            $builder = $builder->whereHas('session', fn ($q) => $q->where('user_id', $client_id));
        }

        if (!empty($q = $filters['q'] ?? null)) {
            $builder = $builder->where(function ($query) use ($q) {
                $query->where('title', 'LIKE', "%$q%")
                    ->orWhere('content', 'LIKE', "%$q%");
            });
        }

        return $builder->latest();
    }

    public static function getOwnedNote(User $user, $note_id): SessionNote
    {
        $note = SessionNote::with('session')->find($note_id);

        if (empty($note) || $note->therapist?->user_id != $user->id) {
            throw new ModelNotFoundException("Note not found");
        }

        return $note;
    }

    /**
     * Client-facing surface: only notes explicitly shared with the client.
     */
    public static function sharedNoteFor(TherapySession $session): ?array
    {
        $note = SessionNote::where('session_id', $session->id)
            ->where('shared_with_client', true)
            ->first();

        if (empty($note)) {
            return null;
        }

        return [
            'title' => $note->title,
            'content' => $note->content,
        ];
    }
}
