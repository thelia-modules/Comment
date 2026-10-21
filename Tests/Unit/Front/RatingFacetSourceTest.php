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

namespace Comment\Tests\Unit\Front;

use Comment\Front\RatingFacetSource;
use Comment\Tests\Double\InMemoryRatingMetaStorage;
use PHPUnit\Framework\TestCase;

final class RatingFacetSourceTest extends TestCase
{
    private InMemoryRatingMetaStorage $ratings;

    protected function setUp(): void
    {
        $this->ratings = new InMemoryRatingMetaStorage();
    }

    public function testAThresholdKeepsTheProductsWhoseStoredAverageReachesIt(): void
    {
        $this->rate([1 => 4.6, 2 => 4.0, 3 => 3.99, 4 => 2.0]);

        $source = new RatingFacetSource($this->ratings, ratingScale: 5);

        self::assertSame([1, 2], $source->productIdsRatedAtLeast(4.0));
        self::assertSame([1, 2, 3, 4], $source->productIdsRatedAtLeast(2.0));
    }

    public function testTheThresholdIsBroughtToTheScaleTheShopRatesOn(): void
    {
        // Out of ten, "4 stars & up" means an average of 8 or more.
        $this->rate([1 => 9.0, 2 => 8.0, 3 => 7.5]);

        $source = new RatingFacetSource($this->ratings, ratingScale: 10);

        self::assertSame([1, 2], $source->productIdsRatedAtLeast(4.0));
    }

    public function testOnlyTheProductsAskedAboutAreAnswered(): void
    {
        $this->rate([1 => 5.0, 2 => 5.0, 3 => 5.0]);

        $source = new RatingFacetSource($this->ratings, ratingScale: 5);

        self::assertSame([1, 3], $source->productIdsRatedAtLeast(4.0, [1, 3, 9]));
        self::assertSame([], $source->productIdsRatedAtLeast(4.0, []));
    }

    public function testTheRatingsOfOtherElementsAreNotProductRatings(): void
    {
        $this->ratings->store('content', 1, 5.0, 3);

        self::assertSame([], (new RatingFacetSource($this->ratings, ratingScale: 5))->productIdsRatedAtLeast(2.0));
    }

    /**
     * @param array<int, float> $averages product id => stored average
     */
    private function rate(array $averages): void
    {
        foreach ($averages as $productId => $average) {
            $this->ratings->store('product', $productId, $average, 1);
        }
    }
}
