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

namespace Comment\Tests\Unit\Service;

use Comment\Model\Comment as CommentModel;
use Comment\Service\CommentElementPurger;
use Comment\Tests\Double\InMemoryCommentStorage;
use Comment\Tests\Double\InMemoryRatingMetaStorage;
use PHPUnit\Framework\TestCase;

/**
 * What is left of a product's reviews once the product is gone.
 *
 * A comment names what it is about by a reference pair (`ref` / `ref_id`), which no foreign
 * key can describe: nothing in the database cascades, so a deleted product left its reviews
 * and its memorised average behind, and the front API kept serving them — a public review of
 * something the shop no longer sells.
 */
final class CommentElementPurgerTest extends TestCase
{
    private InMemoryCommentStorage $comments;

    private InMemoryRatingMetaStorage $ratings;

    private CommentElementPurger $purger;

    protected function setUp(): void
    {
        $this->comments = new InMemoryCommentStorage();
        $this->ratings = new InMemoryRatingMetaStorage();
        $this->purger = new CommentElementPurger($this->comments, $this->ratings);
    }

    public function testDeletingAnElementTakesItsCommentsAndItsStoredRatingWithIt(): void
    {
        $this->comments->store(
            $this->comment(1, 'product', 27),
            $this->comment(2, 'product', 27),
        );
        $this->ratings->store('product', 27, 4.0, 2);

        self::assertSame(2, $this->purger->purge('product', 27));

        self::assertSame([], $this->comments->rows(), 'A review of a product that is gone stays public');
        self::assertSame(
            ['average' => null, 'count' => 0],
            $this->ratings->read('product', 27),
            'The memorised average outlived the element it rates'
        );
    }

    public function testTheReviewsOfEveryOtherElementAreLeftAlone(): void
    {
        $this->comments->store(
            $this->comment(1, 'product', 27),
            $this->comment(2, 'product', 28),
            $this->comment(3, 'content', 27),
        );
        $this->ratings->store('product', 28, 5.0, 1);

        $this->purger->purge('product', 27);

        self::assertSame(
            [2, 3],
            array_map(static fn (CommentModel $comment): int => (int) $comment->getId(), array_values($this->comments->rows())),
            'Only the element that was deleted loses its reviews'
        );
        self::assertSame(['average' => 5.0, 'count' => 1], $this->ratings->read('product', 28));
    }

    public function testAnElementThatCarriedNothingIsNotAnError(): void
    {
        self::assertSame(0, $this->purger->purge('product', 404));
    }

    private function comment(int $id, string $ref, int $refId): CommentModel
    {
        $comment = new CommentModel();
        $comment->setId($id);
        $comment->setRef($ref);
        $comment->setRefId($refId);
        $comment->setStatus(CommentModel::ACCEPTED);

        return $comment;
    }
}
