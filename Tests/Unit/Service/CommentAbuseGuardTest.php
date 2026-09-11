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

use Comment\Service\Front\CommentAbuseGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Reporting a comment raises its priority in the moderation queue, which is only worth
 * anything if one visitor counts once: a counter a click can run up is a way of burying a
 * comment, not of flagging one.
 */
final class CommentAbuseGuardTest extends TestCase
{
    public function testAVisitorReportsOneCommentOnce(): void
    {
        $guard = $this->guard();

        self::assertTrue($guard->allows(7));

        $guard->remember(7);

        self::assertFalse($guard->allows(7), 'The same visitor reported this comment already');
    }

    public function testReportingOneCommentDoesNotSpendTheOthers(): void
    {
        $guard = $this->guard();

        $guard->remember(7);

        self::assertTrue($guard->allows(8));
    }

    public function testAVisitorWithNoSessionIsStillBounded(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $guard = new CommentAbuseGuard($this->limiterFactory(false), $requestStack);

        self::assertFalse($guard->allows(7), 'Without a session, the rate limiter is what is left');
    }

    public function testRememberingWithoutASessionDoesNotFail(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $guard = new CommentAbuseGuard($this->limiterFactory(true), $requestStack);
        $guard->remember(7);

        self::assertTrue($guard->allows(7));
    }

    private function guard(bool $limiterAccepts = true): CommentAbuseGuard
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CommentAbuseGuard($this->limiterFactory($limiterAccepts), $requestStack);
    }

    private function limiterFactory(bool $accepts): RateLimiterFactoryInterface
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(
            new RateLimit($accepts ? 1 : 0, new \DateTimeImmutable(), $accepts, 10)
        );

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return $factory;
    }
}
