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

namespace Comment\Tests\Unit\Action;

use Comment\Events\CommentUpdateEvent;
use Comment\Model\Comment;

/**
 * Editing a comment in the back office must not change who wrote it.
 *
 * The back office sends the whole row back on every save, username included, so a listener
 * that decides the author from the presence of a username detaches the comment from its
 * account on the first typo an administrator corrects: the verified purchase is lost, and so
 * is the only handle the personal-data provider has on it.
 */
final class CommentActionUpdateTest extends CommentActionTestCase
{
    public function testCorrectingACommentKeepsItAttachedToItsCustomer(): void
    {
        $stored = (new Comment())
            ->setId(7)
            ->setCustomerId(42)
            ->setUsername('Jean D.')
            ->setRef('product')
            ->setRefId(11)
            ->setStatus(Comment::ACCEPTED);
        $stored->setNew(false);

        $this->storage->store($stored);

        $event = (new CommentUpdateEvent())
            ->setId(7)
            ->setRef('product')
            ->setRefId(11)
            ->setLocale('fr_FR')
            ->setStatus(Comment::ACCEPTED);
        $event->setCustomerId(42);
        $event->setUsername('Jean D.');
        $event->setTitle('Tres bon produit');
        $event->setContent('Le contenu, faute corrigee.');

        $this->action()->update($event);

        self::assertSame(42, $stored->getCustomerId(), 'the comment lost its customer');
        self::assertSame('Jean D.', $stored->getUsername());
        self::assertSame(1, $stored->saveCount);
        self::assertSame($stored, $event->getComment());
    }

    public function testAnUnknownIdChangesNothing(): void
    {
        $event = (new CommentUpdateEvent())->setId(4041);

        $this->action()->update($event);

        self::assertNull($event->getComment());
        self::assertSame([], $this->storage->saved);
    }
}
