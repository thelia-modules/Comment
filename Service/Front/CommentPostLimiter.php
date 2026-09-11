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

namespace Comment\Service\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many comments a visitor may post.
 *
 * A shop configured so that anyone may post exposes the component's save action to whoever
 * finds it, and a comment costs a row and a mail to the shop managers. Two budgets are spent
 * on every attempt, the way the core spends them for a guest registration: one for the
 * visitor, one for the visitor on that element.
 *
 * The limiters are declared by Comment::configureContainer().
 */
final readonly class CommentPostLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.comment_post_per_client')]
        private RateLimiterFactoryInterface $perClientLimiter,
        #[Autowire(service: 'limiter.comment_post_per_element')]
        private RateLimiterFactoryInterface $perElementLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(string $ref, int $refId): bool
    {
        $client = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        if (!$this->perClientLimiter->create($client)->consume()->isAccepted()) {
            return false;
        }

        return $this->perElementLimiter
            ->create($client.'|'.$ref.'|'.$refId)
            ->consume()
            ->isAccepted();
    }
}
