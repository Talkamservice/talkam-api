<?php

namespace App\Constants\Post;

use Illuminate\Database\Eloquent\Model;

class PostConstants
{
    const TEXT = "Text";
    const POLL = "Poll";
    const FILE = "File";
    const IMAGE = "Image";
    const VIDEO = "Video";

    const LIKE = "Like";
    const DISLIKE = "Dislike";

    const TYPES = [
        self::TEXT => self::TEXT,
        self::POLL => self::POLL,
        self::FILE => self::FILE,
        self::VIDEO => self::VIDEO,
        self::IMAGE => self::IMAGE,
    ];

    const REACTIONS = [
        self::LIKE => self::LIKE,
        self::DISLIKE => self::DISLIKE,
    ];

    const STOP_WORDS = [
        'i', 'the', 'is', 'in', 'you', 'and', 'or', 'an', 'as', 'a', 'of', 'to', 'with', 'on', 'for', 'at', 'by', 'it', 'this', 'that'
    ];
}
