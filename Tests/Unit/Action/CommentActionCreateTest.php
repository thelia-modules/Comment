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

use Comment\Events\CommentCreateEvent;
use Comment\Model\Comment;

/**
 * One comment per customer and per element, editable.
 *
 * A customer who posts again on the same product is editing what they already said: the row
 * is rewritten in place. Two rows would both count in the average, and the shop would show
 * the same buyer twice.
 */
final class CommentActionCreateTest extends CommentActionTestCase
{
    public function testACustomerPostingAgainOnTheSameElementRewritesTheirComment(): void
    {
        $existing = $this->acceptedComment();
        $this->storage->store($existing);

        $this->action()->create($this->postBy(42, 'product', 11, 'Finalement, moins bien.', 2, Comment::PENDING));

        self::assertCount(1, $this->storage->rows(), 'a second comment was created');
        self::assertSame(7, $existing->getId());
        self::assertSame('Finalement, moins bien.', $existing->getContent());
        self::assertSame(2, $existing->getRating());
        self::assertSame([$existing], $this->storage->saved);
    }

    public function testAnEditedCommentGoesBackThroughModeration(): void
    {
        $existing = $this->acceptedComment();
        $this->storage->store($existing);

        // The moderation rule is carried by the event: the front sets PENDING when the shop
        // moderates, and the action must not keep the status the accepted row already had.
        $this->action()->create($this->postBy(42, 'product', 11, 'Texte revu.', 5, Comment::PENDING));

        self::assertSame(Comment::PENDING, $existing->getStatus());
    }

    public function testAnEditedCommentKeepsTheAbuseReportsItCollected(): void
    {
        $existing = $this->acceptedComment();
        $existing->setAbuse(3);
        $this->storage->store($existing);

        $this->action()->create($this->postBy(42, 'product', 11, 'Texte revu.', 5, Comment::PENDING));

        self::assertSame(3, $existing->getAbuse());
    }

    public function testTheSameCustomerStillGetsOneCommentPerElement(): void
    {
        $existing = $this->acceptedComment();
        $this->storage->store($existing);

        $this->action()->create($this->postBy(42, 'product', 12, 'Autre produit.', 4, Comment::PENDING));

        self::assertCount(2, $this->storage->rows());
    }

    public function testAnAnonymousPostIsAlwaysANewComment(): void
    {
        $first = new CommentCreateEvent();
        $first->setRef('product');
        $first->setRefId(11);
        $first->setUsername('Visiteur');
        $first->setStatus(Comment::PENDING);

        $this->action()->create($first);
        $this->action()->create($first);

        self::assertCount(2, $this->storage->rows());
    }

    private function acceptedComment(): Comment
    {
        $comment = (new Comment())
            ->setId(7)
            ->setCustomerId(42)
            ->setUsername('Jean D.')
            ->setRef('product')
            ->setRefId(11)
            ->setContent('Tres bien.')
            ->setRating(5)
            ->setStatus(Comment::ACCEPTED);
        $comment->setNew(false);

        return $comment;
    }

    private function postBy(int $customerId, string $ref, int $refId, string $content, int $rating, int $status): CommentCreateEvent
    {
        $event = new CommentCreateEvent();
        $event->setRef($ref);
        $event->setRefId($refId);
        $event->setCustomerId($customerId);
        $event->setUsername('Jean D.');
        $event->setContent($content);
        $event->setRating($rating);
        $event->setStatus($status);
        $event->setVerified(1);
        $event->setLocale('fr_FR');

        return $event;
    }
}
