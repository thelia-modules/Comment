<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace Comment\Api\Filter;

use ApiPlatform\Metadata\Operation;
use Comment\Service\Api\RatingMetaMemo;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\MetaData;

/**
 * Sorts the public product collection on the average rating, with "order[rating]=asc|desc".
 *
 * Reaches the collection through Comment\Api\Mutator\ProductRatingOrderMutator rather than
 * an ApiFilter attribute: the resource belongs to the core, which must keep working with this
 * module uninstalled.
 *
 * Products nobody rated stay in the collection and go last whatever the direction, the way
 * Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter\ProductPriceOrderFilter keeps the
 * priceless ones: sorting a listing is not filtering it.
 *
 * Pagination stability is the caller's business: products routinely share an average, so an
 * "order[ref]=asc" tie-breaker is what keeps one from repeating on a page and vanishing from
 * another.
 */
class ProductRatingOrderFilter extends AbstractFilter
{
    public const ORDER_PROPERTY = 'rating';

    private const ORDER_PARAMETER = 'order';

    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (self::ORDER_PARAMETER !== $property || !\is_array($value)) {
            return;
        }

        $direction = $this->normalizeDirection($value[self::ORDER_PROPERTY] ?? null);

        if (null === $direction) {
            return;
        }

        // The stored average, which is the very figure the payload exposes as
        // CommentRating.ratingAverage: what the visitor sorts by is what the card shows.
        $sortKey = RatingMetaMemo::averageExpression(
            MetaData::PRODUCT_KEY,
            ProductTableMap::COL_ID,
        );

        // A product nobody rated has no row at all, so the subquery answers NULL: send those
        // last in both directions. The expression carries its own parentheses, which ISNULL()
        // needs -- ISNULL(SELECT ...) is a syntax error.
        $query->addAscendingOrderByColumn('ISNULL('.$sortKey.')');

        if (OrderFilter::DIRECTION_ASC === $direction) {
            $query->addAscendingOrderByColumn($sortKey);

            return;
        }

        $query->addDescendingOrderByColumn($sortKey);
    }

    /**
     * A direction the filter does not know is no sort at all: a listing url is shared, indexed
     * and forged, and it must not answer an error.
     */
    private function normalizeDirection(mixed $value): ?string
    {
        if (!\is_scalar($value)) {
            return null;
        }

        $direction = strtoupper((string) $value);

        return \in_array($direction, [OrderFilter::DIRECTION_ASC, OrderFilter::DIRECTION_DESC], true)
            ? $direction
            : null;
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            \sprintf('%s[%s]', self::ORDER_PARAMETER, self::ORDER_PROPERTY) => [
                'property' => self::ORDER_PROPERTY,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilter::DIRECTION_ASC),
                        strtolower(OrderFilter::DIRECTION_DESC),
                    ],
                ],
            ],
        ];
    }
}
