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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How often a visitor may report a comment.
 *
 * Reporting raises a comment in the moderation queue and never takes it off the shop, so the
 * counter is only meaningful if one visitor counts once per comment: the ids already reported
 * are kept in the visitor's session, and a rate limiter bounds whoever comes back without
 * one.
 *
 * The limiter is declared by Comment::configureContainer().
 */
final readonly class CommentAbuseGuard
{
    private const SESSION_KEY = 'comment.reported';

    public function __construct(
        #[Autowire(service: 'limiter.comment_abuse_per_client')]
        private RateLimiterFactoryInterface $abuseLimiter,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Whether this visitor already reported this comment, which costs nothing to answer.
     *
     * Kept apart from allows() so that a caller can turn the control off in the page it
     * renders without spending a token of the limiter for every comment it draws.
     */
    public function alreadyReported(int $commentId): bool
    {
        return \in_array($commentId, $this->reported(), true);
    }

    public function allows(int $commentId): bool
    {
        if ($this->alreadyReported($commentId)) {
            return false;
        }

        $client = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        return $this->abuseLimiter->create($client)->consume()->isAccepted();
    }

    public function remember(int $commentId): void
    {
        $session = $this->session();

        if (null === $session) {
            return;
        }

        $reported = $this->reported();
        $reported[] = $commentId;

        $session->set(self::SESSION_KEY, array_values(array_unique($reported)));
    }

    /**
     * @return list<int>
     */
    public function reported(): array
    {
        $reported = $this->session()?->get(self::SESSION_KEY, []);

        return \is_array($reported) ? array_map('intval', array_values($reported)) : [];
    }

    /**
     * A front page is served with a session, a stateless API request is not: asked for one
     * that does not exist, the request builds it, and a shop that caches its pages loses the
     * cache with it.
     */
    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }
}
