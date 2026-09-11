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

namespace Comment\Tests\Unit\Api;

use Comment\Api\Resource\Addon\CommentRating;
use Comment\Service\Api\RatingSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Thelia\Api\Resource\Product;

/**
 * The two fields the product resource of the front API gains.
 *
 * A theme reads them to decide whether to draw stars at all, so the state "no accepted
 * comment" has to come out as a null average and not as a zero: a product nobody rated
 * would otherwise be shown as rated zero out of five.
 */
final class CommentRatingTest extends TestCase
{
    public function testItExtendsTheProductResource(): void
    {
        self::assertSame(Product::class, CommentRating::getResourceParent());
    }

    public function testTheTwoFieldsAreReadableOnTheFront(): void
    {
        $groups = [];

        foreach (['ratingAverage', 'ratingCount'] as $property) {
            $attributes = (new \ReflectionProperty(CommentRating::class, $property))
                ->getAttributes(\Symfony\Component\Serializer\Annotation\Groups::class);

            self::assertCount(1, $attributes, \sprintf('%s carries no serialization group', $property));

            $groups[$property] = $attributes[0]->getArguments()[0];
        }

        foreach ($groups as $property => $propertyGroups) {
            self::assertContains(
                Product::GROUP_FRONT_READ,
                $propertyGroups,
                \sprintf('%s is missing from the front collection read group, so a product list would not carry it', $property)
            );
            self::assertContains(
                Product::GROUP_FRONT_READ_SINGLE,
                $propertyGroups,
                \sprintf('%s is missing from the front single read group', $property)
            );
        }
    }

    /**
     * "No rating yet" has to reach the client as a null, which means the key has to be in the
     * payload at all. API Platform normalizes with `skip_null_values` on, so a null property
     * is dropped and the client reads an undefined field instead of the null the contract
     * promises.
     */
    public function testTheAbsenceOfARatingIsSerialisedRatherThanDropped(): void
    {
        $attributes = (new \ReflectionProperty(CommentRating::class, 'ratingAverage'))
            ->getAttributes(Context::class);

        self::assertCount(1, $attributes, 'ratingAverage has nothing keeping its null in the payload');

        $context = $attributes[0]->newInstance()->getNormalizationContext();

        self::assertArrayHasKey(AbstractObjectNormalizer::SKIP_NULL_VALUES, $context);
        self::assertFalse($context[AbstractObjectNormalizer::SKIP_NULL_VALUES]);
    }

    public function testAnElementWithRatingsCarriesTheStoredValues(): void
    {
        $addon = (new CommentRating())->fromSnapshot(new RatingSnapshot(4.5, 2));

        self::assertSame(4.5, $addon->ratingAverage);
        self::assertSame(2, $addon->ratingCount);
    }

    public function testAnElementWithoutRatingsCarriesNullAndZero(): void
    {
        $addon = (new CommentRating())->fromSnapshot(RatingSnapshot::none());

        self::assertNull($addon->ratingAverage, 'The average of an unrated element must be null, never 0');
        self::assertSame(0, $addon->ratingCount);
    }

    public function testTheDefaultsAreAlreadyTheUnratedState(): void
    {
        // The bridge builds the addon with `new` and only fills it when the groups are read:
        // whatever happens, the payload must not claim a rating of zero.
        $addon = new CommentRating();

        self::assertNull($addon->ratingAverage);
        self::assertSame(0, $addon->ratingCount);
    }

    /**
     * The relation is polymorphic (`ref` / `ref_id` on the comment table, and the average
     * lives in `meta_data`), so there is no foreign key for the trait to join on: its
     * default extendQuery() throws, and this one has to stay a no-op.
     */
    public function testExtendQueryIsNeutralised(): void
    {
        $query = $this->createStub(\Propel\Runtime\ActiveQuery\ModelCriteria::class);

        CommentRating::extendQuery($query);

        self::assertNull(CommentRating::getPropelRelatedTableMap());
    }

    public function testARatingIsNeverWrittenThroughTheProductResource(): void
    {
        $addon = new CommentRating();

        $addon->doSave(
            $this->createStub(\Propel\Runtime\ActiveRecord\ActiveRecordInterface::class),
            $this->createStub(\Thelia\Api\Resource\PropelResourceInterface::class),
        );

        self::assertSame(0, $addon->ratingCount);
    }

    /**
     * ActiveRecordInterface declares no getId(). Every generated model has one, a double of
     * the interface does not, and a delete that asks for it blindly dies with a fatal.
     */
    public function testARowThatCannotNameItselfIsLeftAlone(): void
    {
        $addon = new CommentRating();

        $addon->doDelete(
            $this->createStub(\Propel\Runtime\ActiveRecord\ActiveRecordInterface::class),
            $this->createStub(\Thelia\Api\Resource\PropelResourceInterface::class),
        );

        $addon->buildFromModel(
            $this->createStub(\Propel\Runtime\ActiveRecord\ActiveRecordInterface::class),
            $this->createStub(\Thelia\Api\Resource\PropelResourceInterface::class),
        );

        self::assertNull($addon->ratingAverage);
        self::assertSame(0, $addon->ratingCount);
    }
}
