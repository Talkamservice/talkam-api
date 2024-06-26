<?php

namespace App\Constants\Messaging;

use App\Constants\General\StatusConstants;

class MessagingConstants
{
    const TEXT = "Text";
    const MESSAGE_TYPES = [
        self::TEXT => self::TEXT,
    ];
    const CONVERSATION_ACTIONS = [
        StatusConstants::ACCEPTED => StatusConstants::ACCEPTED,
        StatusConstants::REJECTED => StatusConstants::REJECTED,
    ];
}
