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
use Thelia\Model\MetaData;

/**
 * Every Propel query behind the back-office comment screens.
 *
 * The Smarty back-office read its rows through {loop type="comment"}; the Twig back-office
 * has no loops, so the controller asks this repository and hands the result to the template.
 */
final readonly class CommentRepository implements CommentStorageInterface
{
    public const DEFAULT_ORDER = 'created_reverse';

    public function findById(int $id): ?Comment
    {
        return CommentQuery::create()->findPk($id);
    }

    public function save(Comment $comment): void
    {
        $comment->save();
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
     * The products each of these customers has already commented on.
     *
     * Feeds the "please review your order" mail: a customer who already said what they think
     * about a product must not be asked again. Lives here rather than in the action, which built
     * the same query by hand, because reading comments is this class's job.
     *
     * @param list<int> $customerIds
     *
     * @return array<int, list<int>> product ids, keyed by customer id
     */
    public function findCommentedProductIdsByCustomer(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }

        $rows = CommentQuery::create()
            ->filterByCustomerId($customerIds, Criteria::IN)
            ->filterByRef(MetaData::PRODUCT_KEY)
            ->select(['CustomerId', 'RefId'])
            ->find()
            ->toArray();

        $commented = [];

        foreach ($rows as $row) {
            $commented[(int) $row['CustomerId']][] = (int) $row['RefId'];
        }

        // A customer who commented twice on the same product must appear once.
        return array_map(
            static fn (array $productIds): array => array_values(array_unique($productIds)),
            $commented,
        );
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
