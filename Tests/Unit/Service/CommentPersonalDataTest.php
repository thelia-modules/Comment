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

namespace Comment\Tests\Unit\Service;

use Comment\Model\Comment;
use Comment\Service\Customer\CommentPersonalData;
use Comment\Tests\Double\InMemoryCommentStorage;
use PHPUnit\Framework\TestCase;

/**
 * A customer's comments, in their personal data export and after their account is anonymized.
 *
 * A comment carries a name and an address the customer gave, so it is personal data: it has
 * to come out in the export, and it has to lose the name and the address when the account is
 * anonymized. It must not be deleted: a refused or reported comment is the trace of a
 * moderation decision, and an accepted one weighs in the element's average.
 */
final class CommentPersonalDataTest extends TestCase
{
    private InMemoryCommentStorage $storage;

    private CommentPersonalData $personalData;

    protected function setUp(): void
    {
        $this->storage = new InMemoryCommentStorage();
        $this->personalData = new CommentPersonalData($this->storage);
    }

    public function testTheExportCarriesTheCommentsOfThatCustomerOnly(): void
    {
        $this->storage->store($this->comment(1, 42), $this->comment(2, 43));

        $exported = $this->personalData->export(42);

        self::assertCount(1, $exported);
        self::assertSame(1, $exported[0]['id']);
        self::assertSame('Jean D.', $exported[0]['username']);
        self::assertSame('jean@example.com', $exported[0]['email']);
        self::assertSame('Tres bien.', $exported[0]['content']);
        self::assertSame('product', $exported[0]['reference']);
        self::assertSame(11, $exported[0]['reference_id']);
        self::assertSame(5, $exported[0]['rating']);
        self::assertSame('2026-09-01T10:00:00+00:00', $exported[0]['created_at']);
    }

    public function testACustomerWithoutACommentExportsNothing(): void
    {
        self::assertSame([], $this->personalData->export(42));
    }

    public function testAnonymizingRemovesTheNameAndTheAddressButKeepsTheComment(): void
    {
        $mine = $this->comment(1, 42);
        $other = $this->comment(2, 43);
        $this->storage->store($mine, $other);

        $this->personalData->anonymize(42);

        self::assertCount(2, $this->storage->rows(), 'a comment was deleted');
        self::assertSame(CommentPersonalData::ANONYMIZED_USERNAME, $mine->getUsername());
        self::assertNull($mine->getEmail());
        self::assertSame('Tres bien.', $mine->getContent(), 'the comment itself must stay');
        self::assertSame([$mine], $this->storage->saved);
    }

    public function testAnotherCustomerIsLeftAlone(): void
    {
        $other = $this->comment(2, 43);
        $this->storage->store($other);

        $this->personalData->anonymize(42);

        self::assertSame('Jean D.', $other->getUsername());
        self::assertSame([], $this->storage->saved);
    }

    /**
     * The core refuses two providers claiming the same section, and it already uses
     * customer, addresses, orders, carts and newsletter.
     */
    public function testTheSectionNameIsItsOwn(): void
    {
        self::assertSame('comments', CommentPersonalData::SECTION);
    }

    private function comment(int $id, int $customerId): Comment
    {
        $comment = (new Comment())
            ->setId($id)
            ->setCustomerId($customerId)
            ->setUsername('Jean D.')
            ->setEmail('jean@example.com')
            ->setRef('product')
            ->setRefId(11)
            ->setTitle('Parfait')
            ->setContent('Tres bien.')
            ->setRating(5)
            ->setStatus(Comment::ACCEPTED)
            ->setVerified(1)
            ->setCreatedAt(new \DateTimeImmutable('2026-09-01 10:00:00', new \DateTimeZone('UTC')));
        $comment->setNew(false);

        return $comment;
    }
}
