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
use Comment\Service\BackOffice\CommentModerationLog;
use PHPUnit\Framework\TestCase;

/**
 * What moderating a comment writes in admin_log.
 *
 * Accepting, refusing or turning comments off on an element is an administration decision and
 * nothing used to record who took it. The wording is deliberately in English and built from
 * the status code rather than its translated label: an audit trail read months later must not
 * depend on the locale the administrator happened to be using.
 */
final class CommentModerationLogTest extends TestCase
{
    public function testEachStatusIsNamed(): void
    {
        self::assertSame('Comment 7 set to pending', CommentModerationLog::statusChangeMessage(7, Comment::PENDING));
        self::assertSame('Comment 7 set to accepted', CommentModerationLog::statusChangeMessage(7, Comment::ACCEPTED));
        self::assertSame('Comment 7 set to refused', CommentModerationLog::statusChangeMessage(7, Comment::REFUSED));
        self::assertSame('Comment 7 set to abused', CommentModerationLog::statusChangeMessage(7, Comment::ABUSED));
    }

    public function testAStatusTheModelDoesNotKnowIsStillRecorded(): void
    {
        self::assertSame('Comment 7 set to unknown status 9', CommentModerationLog::statusChangeMessage(7, 9));
    }

    public function testTurningCommentsOnAndOffOnAnElement(): void
    {
        self::assertSame(
            'Comments enabled on product 11',
            CommentModerationLog::activationMessage('product', 11, '1')
        );
        self::assertSame(
            'Comments disabled on product 11',
            CommentModerationLog::activationMessage('product', 11, '0')
        );
        self::assertSame(
            'Comments on product 11 back to the shop setting',
            CommentModerationLog::activationMessage('product', 11, '-1')
        );
    }
}
