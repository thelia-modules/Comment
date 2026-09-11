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

namespace Comment\Tests\Unit\Action;

use Comment\Events\CommentComputeRatingEvent;

/**
 * The stored average and the stored number of ratings.
 *
 * AVG() over no row is NULL, which is the state an element reaches as soon as its last rated
 * comment is refused or deleted. Skipping the write on NULL leaves the previous average in
 * meta_data forever: the product page keeps advertising three stars for comments nobody can
 * read any more.
 */
final class CommentActionRatingTest extends CommentActionTestCase
{
    public function testRefusingTheLastRatedCommentClearsTheStoredAverage(): void
    {
        $this->storage->aggregate = ['average' => null, 'count' => 0];

        $event = (new CommentComputeRatingEvent())->setRef('product')->setRefId(11);

        $this->action()->productRatingCompute($event);

        self::assertSame([['ref' => 'product', 'refId' => 11]], $this->ratingMeta->cleared);
        self::assertSame([], $this->ratingMeta->stored);
        self::assertNull($event->getRating());
    }

    public function testTheAverageAndTheNumberOfRatingsAreBothStored(): void
    {
        $this->storage->aggregate = ['average' => 3.5, 'count' => 2];

        $event = (new CommentComputeRatingEvent())->setRef('product')->setRefId(11);

        $this->action()->productRatingCompute($event);

        self::assertSame(
            [['ref' => 'product', 'refId' => 11, 'average' => 3.5, 'count' => 2]],
            $this->ratingMeta->stored
        );
        self::assertSame([], $this->ratingMeta->cleared);
        self::assertSame(3.5, $event->getRating());
    }

    public function testTheAverageIsRoundedToTwoDecimals(): void
    {
        $this->storage->aggregate = ['average' => 10 / 3, 'count' => 3];

        $event = (new CommentComputeRatingEvent())->setRef('product')->setRefId(11);

        $this->action()->productRatingCompute($event);

        self::assertSame(3.33, $this->ratingMeta->stored[0]['average']);
    }

    public function testAnotherKindOfElementIsLeftAlone(): void
    {
        $event = (new CommentComputeRatingEvent())->setRef('content')->setRefId(4);

        $this->action()->productRatingCompute($event);

        self::assertSame([], $this->ratingMeta->stored);
        self::assertSame([], $this->ratingMeta->cleared);
    }
}
