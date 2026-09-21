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

namespace Comment\Api\Resource;

use ApiPlatform\Metadata\Operation;
use Comment\Model\Comment;
use Comment\Model\Map\CommentTableMap;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;
use Thelia\Model\MetaData;
use Thelia\Model\Map\ProductTableMap;

/**
 * The reviews of a product, as the public product payload carries them.
 *
 * The core knows nothing of this module: the addon mechanism is what lets an installed module
 * add a key to a native resource. The short name of this class IS that key, so the payload
 * reads `CommentRating: {ratingAverage, ratingCount}` and renaming the class renames the
 * public API.
 *
 * Both figures come from the comments themselves rather than from the average the module
 * denormalizes into meta_data (Comment\Action\CommentAction::productRatingCompute): that row is
 * only written when the average is not null, so refusing or deleting the last comment of a
 * product leaves the previous average behind, and it carries no count.
 */
final class CommentRating implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    private const AVERAGE_VIRTUAL_COLUMN = 'comment_rating_average';

    private const COUNT_VIRTUAL_COLUMN = 'comment_rating_count';

    /**
     * Alias of the comment table inside the correlated subqueries. Not a join of the outer
     * query: the outer query must keep yielding exactly one row per product.
     */
    private const SUBQUERY_ALIAS = 'comment_rating';

    /**
     * The average of the ratings the product was given, null when nobody rated it.
     *
     * Never 0: 0 is not a rating the form can post (Comment\Form\Field\RatingType rates from 1),
     * so it would read as the worst score there is rather than as the absence of one. A listing
     * that sorts on this pushes the unrated products to the end on its own.
     */
    #[Groups([Product::GROUP_FRONT_READ])]
    public ?float $ratingAverage = null;

    /**
     * How many ratings that average is made of, so a 5/5 from one customer can be told apart
     * from a 5/5 from two hundred.
     */
    #[Groups([Product::GROUP_FRONT_READ])]
    public ?int $ratingCount = null;

    public static function getResourceParent(): string
    {
        return Product::class;
    }

    /**
     * The SQL expression yielding the average rating of the product of the surrounding query.
     *
     * Public because the sort filter orders on it: the column a visitor sorts by has to be the
     * very one the card shows, and two copies of this expression would drift apart.
     */
    public static function averageExpression(): string
    {
        return self::aggregateExpression('AVG');
    }

    public static function countExpression(): string
    {
        return self::aggregateExpression('COUNT');
    }

    /**
     * A correlated subquery rather than a join with a GROUP BY.
     *
     * A join would multiply the product rows, so it would need a GROUP BY to be readable again.
     * The one the collection gets today comes from Thelia\Api\Bridge\Propel\Extension\FilterExtension,
     * which only adds it when the operation carries filters -- and this addon is applied to every
     * operation of the Product resource, admin ones included. A subquery needs no grouping at all,
     * so it holds wherever the addon is applied.
     *
     * Only accepted comments count, and only those filed against a product: the ref column makes
     * the comment table polymorphic, so without it the review of the content #12 would land on
     * the product #12.
     */
    private static function aggregateExpression(string $function): string
    {
        $alias = self::SUBQUERY_ALIAS;
        $comments = CommentTableMap::TABLE_NAME;
        $products = ProductTableMap::TABLE_NAME;

        // COUNT takes the rating rather than the row: a comment posted without a rating is not
        // part of the average, so counting it would caption the stars with a number they do not
        // account for.
        return \sprintf(
            '(SELECT %s(%s.rating) FROM %s AS %s WHERE %s.ref = \'%s\' AND %s.ref_id = %s.id AND %s.status = %d)',
            $function,
            $alias,
            $comments,
            $alias,
            $alias,
            MetaData::PRODUCT_KEY,
            $alias,
            $products,
            $alias,
            Comment::ACCEPTED,
        );
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        $query
            ->withColumn(self::averageExpression(), self::AVERAGE_VIRTUAL_COLUMN)
            ->withColumn(self::countExpression(), self::COUNT_VIRTUAL_COLUMN);
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        // A product nobody rated answers NULL on both columns, which stays null rather than
        // becoming 0: the front tells "not rated yet" from "rated zero" on this alone.
        $average = $activeRecord->hasVirtualColumn(self::AVERAGE_VIRTUAL_COLUMN)
            ? $activeRecord->getVirtualColumn(self::AVERAGE_VIRTUAL_COLUMN)
            : null;

        $count = $activeRecord->hasVirtualColumn(self::COUNT_VIRTUAL_COLUMN)
            ? $activeRecord->getVirtualColumn(self::COUNT_VIRTUAL_COLUMN)
            : null;

        $this->ratingAverage = null === $average ? null : round((float) $average, 2);
        $this->ratingCount = null === $count ? null : (int) $count;

        return $this;
    }

    /**
     * Read-only: a rating is the result of the comments a shop received, never something a
     * client writes on the product.
     */
    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    /**
     * Read-only, and deliberately not left to the trait: Thelia\Api\Bridge\Propel\State\PropelPersistProcessor
     * calls doSave() as soon as a write carries the `CommentRating` key, and the trait would then
     * look for a Propel table this addon does not have and throw.
     */
    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
    }
}
