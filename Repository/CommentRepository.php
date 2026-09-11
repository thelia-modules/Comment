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

    /**
     * Every order the list may be sorted by, in the order a moderator is offered them.
     *
     * @var list<string>
     */
    public const ORDERS = [
        'created_reverse',
        'created',
        'abuse_reverse',
        'abuse',
        'rating_reverse',
        'rating',
        'status',
    ];

    public function findById(int $id): ?Comment
    {
        return CommentQuery::create()->findPk($id);
    }

    public function findOneByCustomerAndReference(int $customerId, string $ref, int $refId): ?Comment
    {
        return CommentQuery::create()
            ->filterByCustomerId($customerId)
            ->filterByRef($ref)
            ->filterByRefId($refId)
            ->orderById(Criteria::DESC)
            ->findOne();
    }

    /**
     * @return list<Comment>
     */
    public function findByCustomer(int $customerId): array
    {
        return CommentQuery::create()
            ->filterByCustomerId($customerId)
            ->orderById(Criteria::ASC)
            ->find()
            ->getData();
    }

    public function save(Comment $comment): void
    {
        $comment->save();
    }

    /**
     * @return array{items: list<Comment>, total: int}
     */
    public function searchAccepted(string $ref, int $refId, int $page, int $limit): array
    {
        return $this->search(
            ref: $ref,
            refId: $refId,
            status: Comment::ACCEPTED,
            order: self::DEFAULT_ORDER,
            page: $page,
            limit: $limit,
        );
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function acceptedRatingAggregate(string $ref, int $refId): array
    {
        $row = CommentQuery::create()
            ->filterByRef($ref)
            ->filterByRefId($refId)
            ->filterByStatus(Comment::ACCEPTED)
            ->withColumn('AVG(RATING)', 'AVG_RATING')
            ->withColumn('COUNT(RATING)', 'RATING_COUNT')
            ->select(['AVG_RATING', 'RATING_COUNT'])
            ->findOne();

        // One row always comes back from an aggregate, with a null average when there is
        // nothing to average. select() on a computed column hands back the raw driver value,
        // a string for an SQL AVG().
        $average = \is_array($row) ? $row['AVG_RATING'] : null;
        $count = \is_array($row) ? $row['RATING_COUNT'] : 0;

        return [
            'average' => null === $average ? null : (float) $average,
            'count' => (int) $count,
        ];
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

    /**
     * The column and direction one order sorts by, as Propel names them.
     *
     * Kept apart from the query so that what the back office offers and what the repository
     * can actually sort by are comparable without a database.
     *
     * @return array{0: string, 1: string}
     */
    public static function orderFor(string $order): array
    {
        return match ($order) {
            'created' => ['CreatedAt', Criteria::ASC],
            'abuse' => ['Abuse', Criteria::ASC],
            'abuse_reverse' => ['Abuse', Criteria::DESC],
            'rating' => ['Rating', Criteria::ASC],
            'rating_reverse' => ['Rating', Criteria::DESC],
            'status' => ['Status', Criteria::ASC],
            default => ['CreatedAt', Criteria::DESC],
        };
    }

    private function applyOrder(CommentQuery $query, string $order): void
    {
        [$column, $direction] = self::orderFor($order);

        $query->orderBy($column, $direction);

        // Most of the queue shares the same counter or the same rating, and a moderator
        // reading a page twice has to see it in the same order: the date breaks the tie.
        if (\in_array($column, ['Abuse', 'Rating'], true)) {
            $query->orderByCreatedAt(Criteria::DESC);
        }
    }
}
