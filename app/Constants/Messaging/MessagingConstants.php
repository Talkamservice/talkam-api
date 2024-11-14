<?php

namespace App\Constants\Messaging;

use App\Constants\General\StatusConstants;

class MessagingConstants
{
    const TEXT = "Text";
    const FILE = "File";
    const MEDIA = "Media";
    const MESSAGE_TYPES = [
        self::TEXT => self::TEXT,
        self::FILE => self::FILE,
        self::MEDIA => self::MEDIA,
    ];
    const CONVERSATION_ACTIONS = [
        StatusConstants::ACCEPTED => StatusConstants::ACCEPTED,
        StatusConstants::DECLINED => StatusConstants::DECLINED,
    ];
}
