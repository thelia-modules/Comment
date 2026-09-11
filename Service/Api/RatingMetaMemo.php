<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Service\Api;

use Comment\Model\Comment;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;

/**
 * The average rating and the number of ratings of an element, read once per element and
 * per request.
 *
 * Both values are recomputed and stored by Comment\Action\CommentAction every time a
 * comment changes state, so they are read here rather than aggregated: a product list of
 * forty rows would otherwise run forty AVG() over the comment table.
 *
 * Static state rather than a service, because an API resource addon is built by the Propel
 * bridge with `new` and receives no dependency. The store is cleared at the start of every
 * request and command, and right after a recompute — see
 * {@see \Comment\EventListeners\RatingMetaMemoListener}.
 */
final class RatingMetaMemo
{
    /** @var array<string, RatingSnapshot> */
    private static array $snapshots = [];

    public static function forElement(string $ref, int $refId): RatingSnapshot
    {
        $key = $ref.'|'.$refId;

        return self::$snapshots[$key] ??= self::read($ref, $refId);
    }

    public static function reset(): void
    {
        self::$snapshots = [];
    }

    /**
     * One query for both keys: MetaDataQuery::getVal() runs one of its own per key, and this
     * is read once per row of a product list.
     */
    private static function read(string $ref, int $refId): RatingSnapshot
    {
        $rows = MetaDataQuery::create()
            ->filterByElementKey($ref)
            ->filterByElementId($refId)
            ->filterByMetaKey([Comment::META_KEY_RATING, Comment::META_KEY_RATING_COUNT], Criteria::IN)
            ->find();

        $average = null;
        $count = 0;

        /** @var MetaData $row */
        foreach ($rows as $row) {
            // The column holds a serialized value: the raw bytes are not the number.
            $value = $row->getDeserializedValue();

            if (Comment::META_KEY_RATING === $row->getMetaKey()) {
                $average = null === $value ? null : (float) $value;

                continue;
            }

            $count = (int) $value;
        }

        // A count without an average is not a rated element: the two are written and erased
        // together, and an average is what the front decides on.
        if (null === $average) {
            return RatingSnapshot::none();
        }

        return new RatingSnapshot($average, $count);
    }
}
