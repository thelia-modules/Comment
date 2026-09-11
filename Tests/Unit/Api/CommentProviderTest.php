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

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\PaginatorInterface;
use Comment\Api\Resource\Comment as CommentResource;
use Comment\Api\State\CommentProvider;
use Comment\Model\Comment as CommentModel;
use Comment\Service\Api\CommentPayloadMapper;
use Comment\Service\Front\CommentContentSanitizer;
use Comment\Tests\Double\InMemoryCommentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * What the public list of an element's comments hands back.
 *
 * A collection with nothing forced on it leaks: the pending, the refused and the reported
 * are all rows of the same table, and the e-mail address an anonymous visitor typed sits in
 * the same row as their comment.
 */
final class CommentProviderTest extends TestCase
{
    private InMemoryCommentStorage $storage;
    private CommentProvider $provider;

    protected function setUp(): void
    {
        $this->storage = new InMemoryCommentStorage();
        $this->provider = new CommentProvider(
            $this->storage,
            new CommentPayloadMapper(new CommentContentSanitizer()),
        );
    }

    public function testOnlyAcceptedCommentsAreListed(): void
    {
        $this->storage->store(
            $this->comment(1, CommentModel::ACCEPTED, 'Solid chair'),
            $this->comment(2, CommentModel::PENDING, 'Waiting for a moderator'),
            $this->comment(3, CommentModel::REFUSED, 'Refused'),
            $this->comment(4, CommentModel::ABUSED, 'Reported'),
        );

        $titles = array_map(
            static fn (CommentResource $comment): ?string => $comment->title,
            iterator_to_array($this->collection())
        );

        self::assertSame(['Solid chair'], array_values($titles));
    }

    public function testTheEmailOfAnAnonymousAuthorNeverLeaves(): void
    {
        $comment = $this->comment(1, CommentModel::ACCEPTED, 'Solid chair');
        $comment->setEmail('someone@example.com');
        $comment->setUsername('Someone');

        $this->storage->store($comment);

        /** @var list<CommentResource> $items */
        $items = array_values(iterator_to_array($this->collection()));

        self::assertCount(1, $items);
        self::assertSame('Someone', $items[0]->author);
        self::assertStringNotContainsString('someone@example.com', serialize($items[0]));
    }

    public function testTheVerifiedPurchaseMentionTravelsWithTheComment(): void
    {
        $bought = $this->comment(1, CommentModel::ACCEPTED, 'Bought it');
        $bought->setVerified(1);

        $justPassing = $this->comment(2, CommentModel::ACCEPTED, 'Never bought it');
        $justPassing->setVerified(0);

        $this->storage->store($bought, $justPassing);

        /** @var list<CommentResource> $items */
        $items = array_values(iterator_to_array($this->collection()));

        self::assertTrue($items[0]->verified);
        self::assertFalse($items[1]->verified);
    }

    public function testTheContentIsCleanedOnItsWayOut(): void
    {
        $comment = $this->comment(1, CommentModel::ACCEPTED, 'Title');
        $comment->setContent('Great <script>alert(1)</script>chair');

        $this->storage->store($comment);

        /** @var list<CommentResource> $items */
        $items = array_values(iterator_to_array($this->collection()));

        self::assertSame('Great alert(1)chair', $items[0]->content);
    }

    public function testThePageSizeIsBounded(): void
    {
        $comments = [];

        for ($id = 1; $id <= 30; ++$id) {
            $comments[] = $this->comment($id, CommentModel::ACCEPTED, 'Comment '.$id);
        }

        $this->storage->store(...$comments);

        $paginator = $this->collection(['refId' => '1', 'itemsPerPage' => '5000']);

        self::assertInstanceOf(PaginatorInterface::class, $paginator);
        self::assertSame(30.0, $paginator->getTotalItems());
        self::assertLessThanOrEqual(CommentProvider::MAX_ITEMS_PER_PAGE, iterator_count($paginator));
    }

    public function testAListWithoutAnElementIsRefusedRatherThanServed(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->collection(['ref' => 'product']);
    }

    public function testAnUnpublishedCommentIsNotReadableOnItsOwnUrl(): void
    {
        $this->storage->store($this->comment(7, CommentModel::PENDING, 'Waiting'));

        self::assertNull($this->provider->provide(new Get(), ['id' => 7]));
    }

    public function testAnAcceptedCommentIsReadableOnItsOwnUrl(): void
    {
        $this->storage->store($this->comment(7, CommentModel::ACCEPTED, 'Published'));

        $comment = $this->provider->provide(new Get(), ['id' => 7]);

        self::assertInstanceOf(CommentResource::class, $comment);
        self::assertSame('Published', $comment->title);
    }

    /**
     * @param array<string, string> $filters
     */
    private function collection(array $filters = ['refId' => '1']): iterable
    {
        $result = $this->provider->provide(new GetCollection(), [], ['filters' => $filters]);

        self::assertIsIterable($result);

        return $result;
    }

    private function comment(int $id, int $status, string $title): CommentModel
    {
        $comment = new CommentModel();
        $comment
            ->setId($id)
            ->setRef('product')
            ->setRefId(1)
            ->setStatus($status)
            ->setTitle($title)
            ->setContent('Some content')
            ->setUsername('Author '.$id)
            ->setRating(4);

        return $comment;
    }
}
