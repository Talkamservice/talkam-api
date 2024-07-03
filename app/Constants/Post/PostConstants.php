<?php

namespace App\Constants\Post;

use Illuminate\Database\Eloquent\Model;

class PostConstants
{
    const TEXT = "Text";
    const POLL = "Poll";
    const FILE = "File";

    const LIKE = "Like";
    const DISLIKE = "Dislike";

    const TYPES = [
        self::TEXT => self::TEXT,
        self::POLL => self::POLL,
        self::FILE => self::FILE,
    ];

    const REACTIONS = [
        self::LIKE => self::LIKE,
        self::DISLIKE => self::DISLIKE,
    ];

    const STOP_WORDS = [
        'the', 'is', 'in', 'and', 'or', 'an', 'a', 'of', 'to', 'with', 'on', 'for', 'at', 'by', 'it', 'this', 'that'
    ];
}
