<?php

namespace App\Services\Therapist;

use App\Constants\Post\PostCategoryConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\SessionNote;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
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

        return SessionNote::updateOrCreate([
            'session_id' => $session->id,
        ], [
            'therapist_id' => $session->therapist_id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'shared_with_client' => $validated['shared_with_client'] ?? false,
            'status' => $validated['status'] ?? 'final',
            'tags' => $validated['tags'] ?? null,
        ]);
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
