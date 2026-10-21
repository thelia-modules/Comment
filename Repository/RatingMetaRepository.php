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

    /**
     * Compares the stored average with the cast RatingMetaMemo::averageExpression() orders on,
     * so that the rating facet and the best-rated order read the same figure.
     */
    public function elementIdsRatedAtLeast(string $ref, float $minimumAverage, ?array $amongIds = null): array
    {
        $query = MetaDataQuery::create()
            ->filterByMetaKey(Comment::META_KEY_RATING)
            ->filterByElementKey($ref)
            ->where('CAST(MetaData.Value AS DECIMAL(10, 2)) >= ?', (string) $minimumAverage, \PDO::PARAM_STR);

        if ($amongIds !== null) {
            if ($amongIds === []) {
                return [];
            }

            $query->filterByElementId($amongIds, Criteria::IN);
        }

        return array_map('intval', $query->select(['ElementId'])->find()->getData());
    }
}
