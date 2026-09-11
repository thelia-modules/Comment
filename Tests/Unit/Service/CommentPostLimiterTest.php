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

use Comment\Service\Front\CommentPostLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * How many comments one visitor may post.
 *
 * With "anyone may post", the LiveComponent's save action is a public door: nothing used to
 * stop a script from replaying it. Two budgets are spent on every post, one for the visitor
 * and one for the visitor on that element.
 */
final class CommentPostLimiterTest extends TestCase
{
    public function testAVisitorIsStoppedOnceTheirBudgetIsSpent(): void
    {
        $limiter = $this->limiter(perClient: 2, perElement: 10);

        self::assertTrue($limiter->allows('product', 11));
        self::assertTrue($limiter->allows('product', 12));
        self::assertFalse($limiter->allows('product', 13), 'a third post went through');
    }

    public function testTheBudgetIsSpentPerElementToo(): void
    {
        $limiter = $this->limiter(perClient: 10, perElement: 1);

        self::assertTrue($limiter->allows('product', 11));
        self::assertFalse($limiter->allows('product', 11), 'the same element took a second post');
        self::assertTrue($limiter->allows('product', 12), 'another element was refused as well');
    }

    public function testAnotherVisitorHasTheirOwnBudget(): void
    {
        $clientStorage = new InMemoryStorage();
        $elementStorage = new InMemoryStorage();

        $first = $this->limiter(perClient: 1, perElement: 1, ip: '10.0.0.1', clientStorage: $clientStorage, elementStorage: $elementStorage);
        $second = $this->limiter(perClient: 1, perElement: 1, ip: '10.0.0.2', clientStorage: $clientStorage, elementStorage: $elementStorage);

        self::assertTrue($first->allows('product', 11));
        self::assertFalse($first->allows('product', 11));
        self::assertTrue($second->allows('product', 11));
    }

    private function limiter(
        int $perClient,
        int $perElement,
        string $ip = '10.0.0.1',
        ?InMemoryStorage $clientStorage = null,
        ?InMemoryStorage $elementStorage = null,
    ): CommentPostLimiter {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]));

        return new CommentPostLimiter(
            new RateLimiterFactory(
                ['id' => 'comment_post_per_client', 'policy' => 'sliding_window', 'limit' => $perClient, 'interval' => '1 hour'],
                $clientStorage ?? new InMemoryStorage(),
            ),
            new RateLimiterFactory(
                ['id' => 'comment_post_per_element', 'policy' => 'sliding_window', 'limit' => $perElement, 'interval' => '1 hour'],
                $elementStorage ?? new InMemoryStorage(),
            ),
            $requestStack,
        );
    }
}
