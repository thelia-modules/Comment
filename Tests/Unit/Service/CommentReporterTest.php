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

use Comment\Events\CommentAbuseEvent;
use Comment\Events\CommentEvents;
use Comment\Model\Comment as CommentModel;
use Comment\Service\Front\CommentAbuseGuard;
use Comment\Service\Front\CommentReporter;
use Comment\Tests\Double\InMemoryCommentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Reporting a comment from the shop.
 *
 * The counter a report raises is what sorts the moderation queue, so it is only worth
 * anything if it counts visitors: the same visitor reporting twice, a comment that is not on
 * the shop, or an id that names nothing must all leave the counter where it is.
 */
final class CommentReporterTest extends TestCase
{
    private InMemoryCommentStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryCommentStorage();
    }

    public function testAReportRaisesTheCounterThroughTheModulesOwnEvent(): void
    {
        $this->storage->store($this->comment(7, CommentModel::ACCEPTED));

        $dispatcher = $this->spyDispatcher();

        self::assertTrue($this->reporter($dispatcher)->report(7));

        self::assertCount(1, $dispatcher->dispatched);
        [$name, $event] = $dispatcher->dispatched[0];

        self::assertSame(CommentEvents::COMMENT_ABUSE, $name);
        self::assertInstanceOf(CommentAbuseEvent::class, $event);
        self::assertSame(7, (int) $event->getId());
    }

    public function testTheSameVisitorReportsTheSameCommentOnce(): void
    {
        $this->storage->store($this->comment(7, CommentModel::ACCEPTED));

        $dispatcher = $this->spyDispatcher();
        $reporter = $this->reporter($dispatcher);

        self::assertTrue($reporter->report(7));
        self::assertFalse($reporter->report(7), 'The second click on the same comment counts for nothing');

        self::assertCount(1, $dispatcher->dispatched);
    }

    public function testACommentWaitingForModerationCannotBeReported(): void
    {
        $this->storage->store($this->comment(7, CommentModel::PENDING));

        $dispatcher = $this->spyDispatcher();

        self::assertFalse($this->reporter($dispatcher)->report(7));
        self::assertSame([], $dispatcher->dispatched);
    }

    public function testAnIdThatNamesNothingIsRefusedRatherThanDispatched(): void
    {
        $dispatcher = $this->spyDispatcher();

        self::assertFalse($this->reporter($dispatcher)->report(404));
        self::assertSame([], $dispatcher->dispatched);
    }

    public function testAVisitorOverTheLimitRaisesNothing(): void
    {
        $this->storage->store($this->comment(7, CommentModel::ACCEPTED));

        $dispatcher = $this->spyDispatcher();

        self::assertFalse($this->reporter($dispatcher, limiterAccepts: false)->report(7));
        self::assertSame([], $dispatcher->dispatched);
    }

    private function comment(int $id, int $status): CommentModel
    {
        $comment = new CommentModel();
        $comment->setId($id);
        $comment->setRef('product');
        $comment->setRefId(1);
        $comment->setStatus($status);

        return $comment;
    }

    private function reporter(EventDispatcherInterface $dispatcher, bool $limiterAccepts = true): CommentReporter
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(
            new RateLimit($limiterAccepts ? 1 : 0, new \DateTimeImmutable(), $limiterAccepts, 10)
        );

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return new CommentReporter(
            $this->storage,
            new CommentAbuseGuard($factory, $requestStack),
            $dispatcher,
        );
    }

    private function spyDispatcher(): object
    {
        return new class implements EventDispatcherInterface {
            /** @var list<array{0: ?string, 1: object}> */
            public array $dispatched = [];

            public function dispatch(object $event, ?string $eventName = null): object
            {
                $this->dispatched[] = [$eventName, $event];

                return $event;
            }
        };
    }
}
