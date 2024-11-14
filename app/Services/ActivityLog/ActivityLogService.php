<?php

namespace App\Services\ActivityLog;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityLogService
{
    public $title;
    public $source;
    public $channel;
    public $description;
    public $tags;
    public $event;
    public $current_data, $previous_data;
    public $url, $type, $activity;
    public $metadata;
    public $admin_id, $model_id, $model;

    /**
     * Set snitch title
     *  @param string $title  Title of snitch;
     *  @return $this
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set snitch source
     *  @param string $source  Where is the snitch from;
     *  @return $this
     */
    public function setSource(string $source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Set snitch channel
     *  @param string $channel  Channel to snitch to;
     *  @return $this
     */
    public function setChannel(string $channel)
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * Set snitch description
     *  @param string $description  Give more info about the snitch;
     *  @return $this
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set snitch tags
     *  @param array $tags  List of tags to add to this snitch;
     *  @return $this
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Set snitch event
     *  @param string $event  Specify the snitch event;
     *  @return $this
     */
    public function setEvent(string $event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Set snitch type
     *  @param string $event  Specify the snitch type;
     *  @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set snitch activity
     *  @param string $event  Specify the snitch activity;
     *  @return $this
     */
    public function setActivity(string $activity)
    {
        $this->activity = $activity;
        return $this;
    }


    /**
     * Set snitch url
     *  @param string $url  Set url for snitch;
     *  @return $this
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }


    /**
     * Set snitch model
     *  @param string $model  Specify the model;
     *  @param int $model_id  Specify the model id;
     *  @return $this
     */
    public function setModel(string $model, int $model_id)
    {
        $this->model = $model;
        $this->model_id = $model_id;
        return $this;
    }

    /**
     * Set snitch data
     *  @param array|null $current_data  Specify the latest data in the model;
     *  @param array|null $previous_data  Specify the previous data in the model before update;
     *  @return $this
     */
    public function setData(array $current_data = null, array $previous_data = null)
    {
        $this->current_data = $current_data;
        $this->previous_data = $previous_data;
        return $this;
    }

    /**
     * Set snitch extra data
     *  @param array|null $current_data  Add extra data to snitch;
     *  @return $this
     */
    public function setMetadata(array $metadata = null)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Set snitch admin
     *  @param $admin_id  Specify the admin performing the action;
     *  @return $this
     */
    public function setAdmin($admin_id)
    {
        $this->admin_id = $admin_id;
        return $this;
    }

    /**
     * Validate data before saving
     *  @return array
     */
    private function validate(): array
    {
        $data = (array) $this;
        $validator = Validator::make($data, [
            "title" => "required|string",
            "event" => "required|string|" . Rule::in(ActivityLogConstants::EVENTS),
            "channel" => "nullable|string|" . Rule::in(ActivityLogConstants::CHANNELS),
            "model" => "nullable|string",
            "model_id" => "nullable|int|required_with:model",
            "admin_id" => "nullable|exists:users,id",
            "previous_data" => "nullable|array",
            "metadata" => "nullable|array",
            "url" => "nullable",
            "type" => "nullable|string|" . Rule::in(array_keys(ActivityLogConstants::TYPES)),
            "description" => "nullable|string",
            "activity" => "nullable|string",
            "source" => "nullable|string|in:internal,external",
            "tags" => "nullable|array",
            "tags.*" => "required|string",

        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Prepare data to snitch record
     *  @return array
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value);
            }
        }
        $data["channel"] = $data["channel"] ?? ActivityLogConstants::CHANNEL_DEFAULT;
        $data["source"] = $data["source"] ?? ActivityLogConstants::SOURCE_INTERNAL;
        return $data;
    }

    /**
     * Save log record
     *  @return \App\Models\ActivityLog
     */
    public function log(): ActivityLog
    {
        if (empty($this->admin_id)) {
            return new ActivityLog;
        }
        $data = $this->validate();
        $data = $this->sanitize($data);
        return ActivityLog::create($data);
    }

    public function list()
    {
        $builder = ActivityLog::latest();
        return $builder;
    }

    public static function delete(string $id)
    {
        $log = ActivityLog::find($id);
        if ($log) {
            $old_log = $log->toArray();
            $log->delete();
        } else {
            throw new ModelNotFoundException("Log not found");
        }


        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("log Deleted")
            ->setDescription((auth()->user()?->full_name ?? auth()->user()?->email) . " deleted a log")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::LOG_DELETED)
            ->setModel(ActivityLog::class, $log->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                ["Old log" => $old_log],
            )
            ->setUrl(request()->fullUrl())
            ->log();
    }
}
