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

namespace Comment\Tests\Unit\Repository;

use Comment\Repository\CommentRepository;
use Comment\Service\BackOffice\CommentOrderCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How the moderation list may be sorted.
 *
 * A reported comment has to be reachable in one click, which is what the "most reported
 * first" order is for: reporting never hides a comment, it only moves it up the queue.
 */
final class CommentOrderTest extends TestCase
{
    public function testTheMostReportedComeFirst(): void
    {
        self::assertSame(['Abuse', 'DESC'], CommentRepository::orderFor('abuse_reverse'));
        self::assertSame(['Abuse', 'ASC'], CommentRepository::orderFor('abuse'));
    }

    public function testTheOrdersTheSmartyBackOfficeUsedStillWork(): void
    {
        self::assertSame(['CreatedAt', 'DESC'], CommentRepository::orderFor('created_reverse'));
        self::assertSame(['CreatedAt', 'ASC'], CommentRepository::orderFor('created'));
        self::assertSame(['Rating', 'ASC'], CommentRepository::orderFor('rating'));
        self::assertSame(['Rating', 'DESC'], CommentRepository::orderFor('rating_reverse'));
        self::assertSame(['Status', 'ASC'], CommentRepository::orderFor('status'));
    }

    public function testAnUnknownOrderFallsBackOnTheMostRecent(): void
    {
        self::assertSame(
            CommentRepository::orderFor(CommentRepository::DEFAULT_ORDER),
            CommentRepository::orderFor('; DROP TABLE comment')
        );
    }

    public function testEveryOrderOfferedToAModeratorIsOneTheRepositoryKnows(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $offered = array_keys((new CommentOrderCatalog($translator))->all());

        self::assertSame(CommentRepository::ORDERS, $offered);

        foreach ($offered as $order) {
            self::assertNotSame(
                [],
                array_filter(CommentRepository::orderFor($order)),
                \sprintf('The "%s" order is offered but maps to nothing', $order)
            );
        }
    }
}
