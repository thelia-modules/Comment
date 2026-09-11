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

use Comment\Events\CommentChangeStatusEvent;
use Comment\Model\Comment;

/**
 * Moderating from the comment list, twice or on a row that is already gone.
 *
 * The back office reads $event->getComment()->getStatus() to answer the browser. A listener
 * that leaves the comment off the event when it has nothing to change turns a double click
 * into a call on null, which is an Error and goes straight past catch (\Exception).
 */
final class CommentActionStatusChangeTest extends CommentActionTestCase
{
    public function testAcceptingAnAlreadyAcceptedCommentStillAnswersWithIt(): void
    {
        $comment = $this->storedComment(Comment::ACCEPTED);

        $event = (new CommentChangeStatusEvent())->setId(7);
        $event->setNewStatus(Comment::ACCEPTED);

        $this->action()->statusChange($event);

        self::assertSame($comment, $event->getComment(), 'the back office has nothing to answer with');
        self::assertSame(Comment::ACCEPTED, $event->getComment()->getStatus());
        self::assertSame(0, $comment->saveCount, 'an unchanged status must not be written again');
    }

    public function testChangingTheStatusWritesItAndAnswersWithTheComment(): void
    {
        $comment = $this->storedComment(Comment::PENDING);

        $event = (new CommentChangeStatusEvent())->setId(7);
        $event->setNewStatus(Comment::REFUSED);

        $this->action()->statusChange($event);

        self::assertSame(Comment::REFUSED, $comment->getStatus());
        self::assertSame([$comment], $this->storage->saved);
        self::assertSame($comment, $event->getComment());
    }

    public function testAnUnknownCommentIsReportedInsteadOfLeavingTheEventEmpty(): void
    {
        $event = (new CommentChangeStatusEvent())->setId(4041);
        $event->setNewStatus(Comment::ACCEPTED);

        $this->expectException(\InvalidArgumentException::class);

        $this->action()->statusChange($event);
    }

    private function storedComment(int $status): Comment
    {
        $comment = (new Comment())
            ->setId(7)
            ->setRef('product')
            ->setRefId(11)
            ->setStatus($status);
        $comment->setNew(false);

        $this->storage->store($comment);

        return $comment;
    }
}
