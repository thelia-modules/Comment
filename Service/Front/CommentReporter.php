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

namespace Comment\Service\Front;

use Comment\Events\CommentAbuseEvent;
use Comment\Events\CommentEvents;
use Comment\Model\Comment as CommentModel;
use Comment\Repository\CommentStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A visitor reporting a comment they find inappropriate.
 *
 * Reporting raises the comment's counter, which is what puts it at the top of the moderation
 * queue; it never takes the comment off the shop. So the whole value of the counter is that
 * it counts visitors and not clicks, and everything below is about that: the visitor must not
 * have reported this comment before, the comment must be one that is actually on the shop,
 * and whoever comes back without a session is still bounded by the rate limiter.
 *
 * The counter itself is raised by the module's own CommentAbuseEvent, which the back office
 * shares: reporting from the shop and reporting from anywhere else go through the same rule.
 */
final readonly class CommentReporter
{
    public function __construct(
        private CommentStorageInterface $commentStorage,
        private CommentAbuseGuard $guard,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * True when the report was recorded, false when it was not worth recording.
     *
     * One answer for every refusal on purpose: a visitor told apart "you already reported
     * this" from "this comment does not exist" is a visitor who can enumerate the table.
     */
    public function report(int $commentId): bool
    {
        // Free, and the most common refusal: a second click on a control the page did not
        // refresh must not cost a query nor a token.
        if ($this->guard->alreadyReported($commentId)) {
            return false;
        }

        $comment = $this->commentStorage->findById($commentId);

        // A pending or refused comment is not on the shop, so nothing out there can carry a
        // control pointing at it.
        if (null === $comment || CommentModel::ACCEPTED !== $comment->getStatus()) {
            return false;
        }

        if (!$this->guard->allows($commentId)) {
            return false;
        }

        $event = new CommentAbuseEvent();
        $event->setId($commentId);

        $this->eventDispatcher->dispatch($event, CommentEvents::COMMENT_ABUSE);

        // Remembered only once the counter was actually raised: a listener that threw would
        // otherwise leave the visitor unable to report a comment nobody flagged.
        $this->guard->remember($commentId);

        return true;
    }
}
