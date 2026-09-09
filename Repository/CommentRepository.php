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

namespace Comment\Repository;

use Comment\Model\Comment;
use Comment\Model\CommentQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Every Propel query behind the back-office comment screens.
 *
 * The Smarty back-office read its rows through {loop type="comment"}; the Twig back-office
 * has no loops, so the controller asks this repository and hands the result to the template.
 */
final readonly class CommentRepository
{
    public const DEFAULT_ORDER = 'created_reverse';

    public function findById(int $id): ?Comment
    {
        return CommentQuery::create()->findPk($id);
    }

    /**
     * @return array{items: list<Comment>, total: int}
     */
    public function search(
        ?string $ref = null,
        ?int $refId = null,
        ?int $status = null,
        string $order = self::DEFAULT_ORDER,
        int $page = 1,
        int $limit = 20,
    ): array {
        $query = CommentQuery::create();

        if (null !== $ref && '' !== $ref) {
            $query->filterByRef($ref);
        }

        if (null !== $refId) {
            $query->filterByRefId($refId);
        }

        if (null !== $status) {
            $query->filterByStatus($status);
        }

        $total = (clone $query)->count();

        $this->applyOrder($query, $order);

        $page = max(1, $page);
        $limit = max(1, $limit);

        $items = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->find()
            ->getData();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<int, int> number of comments per status value
     */
    public function countByStatus(?string $ref = null, ?int $refId = null): array
    {
        $counts = [];

        foreach ([Comment::PENDING, Comment::ACCEPTED, Comment::REFUSED, Comment::ABUSED] as $status) {
            $query = CommentQuery::create()->filterByStatus($status);

            if (null !== $ref && '' !== $ref) {
                $query->filterByRef($ref);
            }

            if (null !== $refId) {
                $query->filterByRefId($refId);
            }

            $counts[$status] = $query->count();
        }

        return $counts;
    }

    private function applyOrder(CommentQuery $query, string $order): void
    {
        match ($order) {
            'created' => $query->orderByCreatedAt(Criteria::ASC),
            'rating' => $query->orderByRating(Criteria::ASC),
            'rating_reverse' => $query->orderByRating(Criteria::DESC),
            'status' => $query->orderByStatus(Criteria::ASC),
            default => $query->orderByCreatedAt(Criteria::DESC),
        };
    }
}
