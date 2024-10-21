<?php

namespace App\Services\General\Webhook;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Jobs\Webhook\ProcessWebhookJob;
use App\Models\HookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebhookService
{
    public static function getById($id): HookLog
    {
        $hook = HookLog::find($id);
        if (empty($hook)) {
            throw new ModelNotFoundException("Hook not found");
        }
        return $hook;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            "source" => "nullable|string|" . Rule::requiredIf(empty($id)),
            "event" => "nullable|string",
            "headers" => "nullable|array|" . Rule::requiredIf(empty($id)),
            "content" => "nullable|array|" . Rule::requiredIf(empty($id)),
            "url" => "nullable|string|" . Rule::requiredIf(empty($id)),
            "delay" => "nullable|numeric",
            "response" => "nullable|array",
            "user_id" => "nullable|exists:users,id",
            "sender_name" => "nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data);
            $hook = HookLog::create($data);
            DB::commit();
            return $hook;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = $this->validate($data, $id);
            $hook = $this->getById($id);
            $hook->update($data);
            DB::commit();
            return $hook;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function list(array $data = [])
    {
        $builder = HookLog::with("user");

        if (!empty($key = $data["source"] ?? null)) {
            $builder = $builder->where("source", $key);
        }

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key);
        }

        return $builder;
    }

    public function dispatch($webhook)
    {
        ProcessWebhookJob::dispatch($webhook->toArray(), $webhook->user_id)->delay(now()->addSeconds($webhook->delay));
    }

    public function retry($webhook_id)
    {
        $webhook = $this->getById($webhook_id);

        if ($webhook->status == StatusConstants::SUCCESSFUL) {
            throw new InvalidRequestException("You cannot retry a successful webhook");
        }

        $webhook->update([
            "retries" => $webhook->retries + 1,
            "latest_retry" => now()
        ]);

        ProcessWebhookJob::dispatch($webhook->toArray(), $webhook->user_id)->delay(now()->addSeconds($webhook->delay));
    }

    public function retryAll(array $webhook_ids = [])
    {
        foreach ($webhook_ids as $key => $webhook_id) {
            $webhook = $this->getById($webhook_id);
            $webhook->update([
                "retries" => $webhook->retries + 1,
                "latest_retry" => now()
            ]);
            ProcessWebhookJob::dispatch($webhook->toArray(), $webhook->user_id)->delay(now()->addSeconds($webhook->delay));
        }
    }
}
