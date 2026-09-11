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

namespace Comment\Service\Customer;

use Comment\Model\Comment;
use Comment\Repository\CommentStorageInterface;

/**
 * A customer's comments, as personal data.
 *
 * A comment carries the name and the address the customer gave, so it belongs in their export
 * and it has to lose both when their account is anonymized. It is not deleted: a refused or a
 * reported comment is the trace of a moderation decision, and an accepted one weighs in the
 * average of the element it is about.
 *
 * Addressed by customer id rather than by a Thelia\Model\Customer, so the rules can be
 * exercised without a built Propel model tree; CommentPersonalDataProvider is the adapter the
 * core calls.
 */
final readonly class CommentPersonalData
{
    /** The section this module claims in the export. The core refuses two providers sharing one. */
    public const SECTION = 'comments';

    /**
     * What replaces the name a comment was published under. The comment stays readable and
     * keeps counting in the average; nothing about it points at a person any more.
     */
    public const ANONYMIZED_USERNAME = 'Anonymous';

    public function __construct(
        private CommentStorageInterface $storage,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function export(int $customerId): array
    {
        return array_map(
            static function (Comment $comment): array {
                $createdAt = $comment->getCreatedAt();

                return [
                    'id' => $comment->getId(),
                    'reference' => $comment->getRef(),
                    'reference_id' => $comment->getRefId(),
                    'username' => $comment->getUsername(),
                    'email' => $comment->getEmail(),
                    'title' => $comment->getTitle(),
                    'content' => $comment->getContent(),
                    'rating' => $comment->getRating(),
                    'status' => $comment->getStatus(),
                    'verified_purchase' => (bool) $comment->getVerified(),
                    'abuse_reports' => (int) $comment->getAbuse(),
                    'created_at' => $createdAt instanceof \DateTimeInterface
                        ? $createdAt->format(\DATE_ATOM)
                        : $createdAt,
                ];
            },
            $this->storage->findByCustomer($customerId),
        );
    }

    public function anonymize(int $customerId): void
    {
        foreach ($this->storage->findByCustomer($customerId) as $comment) {
            // The customer id stays: the account it points at is anonymized by the core in the
            // same transaction, and it is what keeps the verified purchase meaningful.
            $comment
                ->setUsername(self::ANONYMIZED_USERNAME)
                ->setEmail(null);

            $this->storage->save($comment);
        }
    }
}
