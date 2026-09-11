<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Api\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Comment\Api\Resource\Comment as CommentResource;
use Comment\Model\Comment as CommentModel;
use Comment\Repository\CommentStorageInterface;
use Comment\Service\Api\CommentPayloadMapper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Serves the comments of one element, and one comment by its id.
 *
 * The status filter is applied here rather than left to a query parameter: a collection a
 * client can widen is a collection that hands out what a moderator refused. Same for the
 * element — the list is the reviews of a product or of a content, and a request that names
 * none is refused rather than answered with the whole table.
 */
final readonly class CommentProvider implements ProviderInterface
{
    public const DEFAULT_ITEMS_PER_PAGE = 20;
    public const MAX_ITEMS_PER_PAGE = 100;

    public function __construct(
        private CommentStorageInterface $commentStorage,
        private CommentPayloadMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->collection($context['filters'] ?? []);
        }

        $comment = $this->commentStorage->findById((int) ($uriVariables['id'] ?? 0));

        // Null is a 404: a comment waiting for moderation, or one that was refused, is not
        // readable by the visitor who happens to know its id.
        if (null === $comment || CommentModel::ACCEPTED !== $comment->getStatus()) {
            return null;
        }

        return $this->mapper->toResource($comment);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function collection(array $filters): TraversablePaginator
    {
        $refId = (int) ($filters['refId'] ?? 0);

        if ($refId < 1) {
            throw new BadRequestHttpException('The "refId" query parameter is required: the list is the comments of one element.');
        }

        $ref = (string) ($filters['ref'] ?? 'product');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? self::DEFAULT_ITEMS_PER_PAGE);
        $itemsPerPage = min(self::MAX_ITEMS_PER_PAGE, max(1, $itemsPerPage));

        $result = $this->commentStorage->searchAccepted($ref, $refId, $page, $itemsPerPage);

        $comments = array_map(
            fn (CommentModel $comment): CommentResource => $this->mapper->toResource($comment),
            $result['items'],
        );

        return new TraversablePaginator(
            new \ArrayIterator($comments),
            $page,
            $itemsPerPage,
            $result['total'],
        );
    }
}
