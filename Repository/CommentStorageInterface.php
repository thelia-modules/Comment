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

/**
 * The reads and writes the module's listeners need, behind a contract.
 *
 * Comment\Action\CommentAction used to reach for CommentQuery and Comment::save() itself,
 * which made its rules impossible to exercise without a built Propel model tree and a
 * database. They are the rules a shop's data depends on, so they are reached through this
 * interface instead and CommentRepository is the one implementation.
 */
interface CommentStorageInterface
{
    public function findById(int $id): ?Comment;

    /**
     * The comment this customer already left on this element, if any: the most recent one,
     * whatever its moderation status.
     */
    public function findOneByCustomerAndReference(int $customerId, string $ref, int $refId): ?Comment;

    /**
     * Every comment of one customer, whatever its moderation status: what their personal data
     * export has to carry, and what anonymizing their account has to go through.
     *
     * @return list<Comment>
     */
    public function findByCustomer(int $customerId): array;

    public function save(Comment $comment): void;

    /**
     * One page of the accepted comments of an element, most recent first.
     *
     * The status is not a parameter: this is what a visitor may read, and the front API reads
     * nothing else. A refused or pending comment must not become readable because a query
     * parameter asked for it.
     *
     * @return array{items: list<Comment>, total: int}
     */
    public function searchAccepted(string $ref, int $refId, int $page, int $limit): array;

    /**
     * Average rating and number of ratings over the accepted comments of an element.
     *
     * The average is null when no accepted comment carries a rating, which is exactly the
     * state that has to erase what was stored rather than leave it behind.
     *
     * @return array{average: float|null, count: int}
     */
    public function acceptedRatingAggregate(string $ref, int $refId): array;
}
