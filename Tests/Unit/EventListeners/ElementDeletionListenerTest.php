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

namespace Comment\Tests\Unit\EventListeners;

use Comment\EventListeners\ElementDeletionListener;
use Comment\Model\Comment as CommentModel;
use Comment\Service\CommentElementPurger;
use Comment\Tests\Double\InMemoryCommentStorage;
use Comment\Tests\Double\InMemoryRatingMetaStorage;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * The two doors a commentable element leaves by.
 *
 * A product or a content deleted from the back office goes through its own Thelia event, and
 * nothing in the database can follow it: the reference pair a comment carries is not a
 * foreign key. Without this listener the reviews and the memorised average of a deleted
 * element stay in the tables, and the front API keeps serving them.
 */
final class ElementDeletionListenerTest extends TestCase
{
    private InMemoryCommentStorage $comments;

    private InMemoryRatingMetaStorage $ratings;

    private ElementDeletionListener $listener;

    protected function setUp(): void
    {
        $this->comments = new InMemoryCommentStorage();
        $this->ratings = new InMemoryRatingMetaStorage();
        $this->listener = new ElementDeletionListener(
            new CommentElementPurger($this->comments, $this->ratings)
        );
    }

    public function testADeletedProductLeavesNoReviewAndNoStoredRating(): void
    {
        $this->comments->store($this->comment(1, 'product', 27));
        $this->ratings->store('product', 27, 4.0, 1);

        $this->listener->onProductDeleted(new ProductDeleteEvent(27));

        self::assertSame([], $this->comments->rows());
        self::assertSame(['average' => null, 'count' => 0], $this->ratings->read('product', 27));
    }

    public function testADeletedContentLeavesNoReviewEither(): void
    {
        $this->comments->store($this->comment(1, 'content', 12));

        $this->listener->onContentDeleted(new ContentDeleteEvent(12));

        self::assertSame([], $this->comments->rows());
    }

    /**
     * Both run behind the core's own listener, which deletes at priority 128 inside its
     * transaction: a comment must not go before the element it belongs to actually has.
     */
    public function testTheListenersRunAfterTheElementIsActuallyDeleted(): void
    {
        $subscribed = ElementDeletionListener::getSubscribedEvents();

        self::assertSame(['onProductDeleted', -128], $subscribed[TheliaEvents::PRODUCT_DELETE]);
        self::assertSame(['onContentDeleted', -128], $subscribed[TheliaEvents::CONTENT_DELETE]);
    }

    /**
     * The API deletes a product through the Propel bridge, which dispatches no Thelia event at
     * all: the resource addon is the only hook on that path, and it was a no-op.
     */
    public function testTheApiPathPurgesThroughTheResourceAddon(): void
    {
        $addons = \dirname(__DIR__, 3).'/Api/Resource/Addon';

        foreach (['CommentRating.php' => 'product', 'CommentContentPurge.php' => 'content'] as $file => $ref) {
            self::assertMatchesRegularExpression(
                '/public function doDelete\(.*?\).*?CommentElementPurger::standalone\(\)->purge\(self::REF/s',
                (string) file_get_contents($addons.'/'.$file),
                \sprintf('A %s deleted through the API keeps its comments', $ref)
            );
        }
    }

    /**
     * The content addon exists for doDelete() alone. A property in a read group would put it
     * in every content payload, where it has nothing to say.
     */
    public function testTheContentAddonAddsNothingToThePayload(): void
    {
        $properties = (new \ReflectionClass(\Comment\Api\Resource\Addon\CommentContentPurge::class))
            ->getProperties(\ReflectionProperty::IS_PUBLIC);

        self::assertSame([], $properties);
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
