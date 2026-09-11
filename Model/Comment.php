<?php

namespace Comment\Model;

use Comment\Model\Base\Comment as BaseComment;

class Comment extends BaseComment
{
    const PENDING = 0;
    const ACCEPTED = 1;
    const REFUSED = 2;
    const ABUSED = 3;

    const META_KEY_RATING = 'COMMENT_RATING';
    const META_KEY_RATING_COUNT = 'COMMENT_RATING_COUNT';
    const META_KEY_ACTIVATED = 'COMMENT_ACTIVATED';
}
