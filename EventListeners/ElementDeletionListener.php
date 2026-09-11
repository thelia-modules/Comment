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

namespace Comment\EventListeners;

use Comment\Service\CommentElementPurger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Takes an element's comments away with the element.
 *
 * Product and content are the two references comments may be attached to, and both are
 * deleted through their own Thelia event. The reference pair a comment carries is not a
 * foreign key, so this is the only thing that can clean up after them.
 *
 * Both listeners run after the core's own, which deletes at priority 128 inside its
 * transaction: comments go once the element has actually gone, never before.
 */
final readonly class ElementDeletionListener implements EventSubscriberInterface
{
    public function __construct(
        private CommentElementPurger $purger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_DELETE => ['onProductDeleted', -128],
            TheliaEvents::CONTENT_DELETE => ['onContentDeleted', -128],
        ];
    }

    public function onProductDeleted(ProductDeleteEvent $event): void
    {
        $this->purger->purge('product', (int) $event->getProductId());
    }

    public function onContentDeleted(ContentDeleteEvent $event): void
    {
        $this->purger->purge('content', (int) $event->getContentId());
    }
}
