<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Repository;

use Comment\Model\Comment;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\MetaDataQuery;

final readonly class RatingMetaRepository implements RatingMetaStorageInterface
{
    public function store(string $ref, int $refId, float $average, int $count): void
    {
        MetaDataQuery::setVal(Comment::META_KEY_RATING, $ref, $refId, $average);
        MetaDataQuery::setVal(Comment::META_KEY_RATING_COUNT, $ref, $refId, $count);
    }

    public function clear(string $ref, int $refId): void
    {
        MetaDataQuery::create()
            ->filterByMetaKey([Comment::META_KEY_RATING, Comment::META_KEY_RATING_COUNT], Criteria::IN)
            ->filterByElementKey($ref)
            ->filterByElementId($refId)
            ->delete();
    }
}
