<?php

namespace App\Constants\Messaging;

class MessagingV2Constants
{
    const DELETE_FOR_ME = 'for_me';
    const DELETE_FOR_EVERYONE = 'for_everyone';

    const DELETE_TYPES = [
        self::DELETE_FOR_ME,
        self::DELETE_FOR_EVERYONE,
    ];

    const PRESENCE_ONLINE = 'online';
    const PRESENCE_AWAY = 'away';
    const PRESENCE_OFFLINE = 'offline';

    const PRESENCE_STATES = [
        self::PRESENCE_ONLINE,
        self::PRESENCE_AWAY,
        self::PRESENCE_OFFLINE,
    ];
}
