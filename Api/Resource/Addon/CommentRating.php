<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Api\Resource\Addon;

use ApiPlatform\Metadata\Operation;
use Comment\Service\Api\RatingMetaMemo;
use Comment\Service\Api\RatingSnapshot;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;

/**
 * Adds the average rating and the number of ratings of a product to the front API, on
 * `/api/front/products` and on `/api/front/products/{id}`, under the `CommentRating` key.
 *
 * Both values are read as the module stored them; nothing is aggregated here, so a list of
 * forty products costs forty key reads and no AVG() over the comment table.
 *
 * A comment is attached to an element by a reference pair (`ref` / `ref_id`) and the two
 * values live in `meta_data`, so there is no foreign key to join: the trait's JOIN-based
 * extendQuery() cannot apply and is neutralised, the way TheliaLibrary does for its own
 * polymorphic rows.
 */
class CommentRating implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    /** The reference the product rows of the comment table are stored under. */
    private const REF = 'product';

    /**
     * The average rating over the accepted comments, null when there is none.
     *
     * Null rather than zero: a theme draws stars on an average, and zero out of five is a
     * verdict, not the absence of one.
     *
     * The key has to be there carrying that null. API Platform normalizes with
     * `skip_null_values` on, which drops a null property from the payload entirely, and a
     * client reading `ratingAverage` then gets "undefined" where the contract says "no rating
     * yet" — two different things in every language that has both.
     */
    #[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false])]
    #[Groups([Product::GROUP_FRONT_READ, Product::GROUP_FRONT_READ_SINGLE])]
    public ?float $ratingAverage = null;

    /** How many accepted comments carry a rating. */
    #[Groups([Product::GROUP_FRONT_READ, Product::GROUP_FRONT_READ_SINGLE])]
    public int $ratingCount = 0;

    public static function getResourceParent(): string
    {
        return Product::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        // Polymorphic reference and a value stored outside the product table: read in
        // buildFromModel() instead.
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $productId = $activeRecord->getId();

        if (null === $productId) {
            return $this;
        }

        return $this->fromSnapshot(RatingMetaMemo::forElement(self::REF, (int) $productId));
    }

    public function fromSnapshot(RatingSnapshot $snapshot): self
    {
        $this->ratingAverage = $snapshot->average;
        $this->ratingCount = $snapshot->count;

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // Read-only: a rating is the outcome of moderating comments, never something written
        // through the product resource.
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // Read-only: the stored values are erased by the module when the last rated comment
        // goes.
    }
}
