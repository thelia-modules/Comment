<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\EventListeners;

use Comment\Events\CommentEvents;
use Comment\Service\Api\RatingMetaMemo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Empties the rating store, which is static and therefore outlives a request in a worker.
 *
 * Cleared at the start of every request, and again once a recompute has written new values:
 * what the store served before the recompute is no longer what the shop has.
 */
final class RatingMetaMemoListener implements EventSubscriberInterface
{
    public function reset(): void
    {
        RatingMetaMemo::reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['reset', 1024],
            // After Comment\Action\CommentAction::productRatingCompute(), which runs at 128.
            CommentEvents::COMMENT_RATING_COMPUTE => ['reset', -1024],
        ];
    }
}
