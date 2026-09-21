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
use Comment\Repository\CommentRepository;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\MetaData;
use Thelia\Model\Map\MetaDataTableMap;
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
    /**
     * Alias of the meta_data table inside the correlated subquery of averageExpression().
     * Not a join of the outer query: a listing must keep yielding one row per product.
     */
    private const SUBQUERY_ALIAS = 'rating_meta';

    /** @var array<string, RatingSnapshot> */
    private static array $snapshots = [];

    public static function forElement(string $ref, int $refId): RatingSnapshot
    {
        $key = $ref.'|'.$refId;

        return self::$snapshots[$key] ??= self::read($ref, $refId);
    }

    /**
     * The stored average of an element, as SQL, so a listing can order on it.
     *
     * The very row forElement() reads: what a visitor sorts by is what the card shows, and
     * two expressions of the same figure would drift apart. A correlated subquery rather
     * than a join, because the reference pair is polymorphic and carries no foreign key.
     *
     * The value column is LONGTEXT and holds "4.5": ordered as text it would file 10 before
     * 4, so it is cast. Two decimals is what CommentAction rounds the average to.
     *
     * @param string $elementIdColumn the column of the surrounding query holding the element id
     */
    public static function averageExpression(string $ref, string $elementIdColumn): string
    {
        return \sprintf(
            '(SELECT CAST(%1$s.value AS DECIMAL(10, 2)) FROM %2$s AS %1$s'
            .' WHERE %1$s.meta_key = \'%3$s\' AND %1$s.element_key = \'%4$s\''
            .' AND %1$s.element_id = %5$s)',
            self::SUBQUERY_ALIAS,
            MetaDataTableMap::TABLE_NAME,
            Comment::META_KEY_RATING,
            $ref,
            $elementIdColumn,
        );
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
        // Null rather than zero: a missing row and a stored zero have to stay tellable
        // apart, because only the first one is a snapshot to repair.
        $count = null;

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

        // An average whose count row never made it: shops exist whose meta_data holds the
        // first without the second, and nothing repairs them until a comment on the element
        // changes state. Counting live beats answering zero -- an element the theme draws
        // stars for, captioned "0 ratings", contradicts itself. Only the count is taken from
        // the aggregate: the stored average stays the figure the payload has always carried,
        // and the sort orders on that same stored figure.
        if (null === $count) {
            $count = (new CommentRepository())->acceptedRatingAggregate($ref, $refId)['count'];
        }

        return new RatingSnapshot($average, $count);
    }
}
