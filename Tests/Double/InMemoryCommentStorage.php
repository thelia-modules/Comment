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

namespace Comment\Tests\Double;

use Comment\Model\Comment;
use Comment\Repository\CommentStorageInterface;

/**
 * The comment table, in an array.
 *
 * Records what the module asked to persist so a test can assert on it, and hands back the
 * same object on a second read the way Propel's instance pool does.
 */
final class InMemoryCommentStorage implements CommentStorageInterface
{
    /** @var array<int, Comment> */
    private array $rows = [];

    /** @var list<Comment> every comment handed to save(), in order */
    public array $saved = [];

    private int $nextId = 1;

    /** @var array{average: float|null, count: int} what the aggregate query would answer */
    public array $aggregate = ['average' => null, 'count' => 0];

    public function store(Comment ...$comments): void
    {
        foreach ($comments as $comment) {
            $id = $comment->getId() ?? $this->nextId;
            $comment->setId($id);
            $this->rows[$id] = $comment;
            $this->nextId = max($this->nextId, $id + 1);
        }
    }

    /** @return array<int, Comment> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function findById(int $id): ?Comment
    {
        return $this->rows[$id] ?? null;
    }

    public function findOneByCustomerAndReference(int $customerId, string $ref, int $refId): ?Comment
    {
        $found = null;

        foreach ($this->rows as $comment) {
            if ($customerId === $comment->getCustomerId()
                && $ref === $comment->getRef()
                && $refId === $comment->getRefId()
            ) {
                $found = $comment;
            }
        }

        return $found;
    }

    /**
     * @return array{items: list<Comment>, total: int}
     */
    public function searchAccepted(string $ref, int $refId, int $page, int $limit): array
    {
        $accepted = array_values(array_filter(
            $this->rows,
            static fn (Comment $comment): bool => Comment::ACCEPTED === $comment->getStatus()
                && $ref === $comment->getRef()
                && $refId === $comment->getRefId(),
        ));

        return [
            'items' => array_slice($accepted, ($page - 1) * $limit, $limit),
            'total' => \count($accepted),
        ];
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function acceptedRatingAggregate(string $ref, int $refId): array
    {
        return $this->aggregate;
    }

    /**
     * @return list<Comment>
     */
    public function findByCustomer(int $customerId): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (Comment $comment): bool => $customerId === $comment->getCustomerId(),
        ));
    }

    public function save(Comment $comment): void
    {
        $comment->save();
        $this->store($comment);
        $this->saved[] = $comment;
    }

    public function deleteByReference(string $ref, int $refId): int
    {
        $deleted = 0;

        foreach ($this->rows as $id => $comment) {
            if ($ref === $comment->getRef() && $refId === $comment->getRefId()) {
                unset($this->rows[$id]);
                ++$deleted;
            }
        }

        return $deleted;
    }
}
