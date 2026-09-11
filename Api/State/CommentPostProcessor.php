<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Comment\Api\Resource\Comment as CommentResource;
use Comment\Comment as CommentModule;
use Comment\Events\CommentCreateEvent;
use Comment\Events\CommentEvents;
use Comment\Model\Comment as CommentModel;
use Comment\Service\Api\CommentPayloadMapper;
use Comment\Service\Front\CommentDefinitionResolverInterface;
use Comment\Service\Front\CommentPostLimiter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

/**
 * Posting a comment through the front API.
 *
 * Same door as the theme's form: the rate limiter spends its budget first, the module's own
 * definition says whether this visitor may post on this element, and the comment is created
 * by COMMENT_CREATE rather than written here — which is what keeps the one-comment-per-
 * customer rule, the moderation status and the notification mail in one place.
 */
final readonly class CommentPostProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CommentDefinitionResolverInterface $definitionResolver,
        private CommentPostLimiter $postLimiter,
        private CommentPayloadMapper $mapper,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommentResource
    {
        if (!$data instanceof CommentResource) {
            throw new UnprocessableEntityHttpException('A comment is expected.');
        }

        $ref = (string) $data->ref;
        $refId = (int) $data->refId;

        // Before any query: a replayed request has to cost as little as possible.
        if (!$this->postLimiter->allows($ref, $refId)) {
            throw new TooManyRequestsHttpException(null, 'Too many comments have been sent from here. Please try again later.');
        }

        $definition = $this->definitionResolver->resolve($ref, $refId, $this->customer());

        // Comments turned off for the shop or for this element: there is nothing to post to.
        if ($definition->hidden) {
            throw new NotFoundHttpException((string) $definition->message);
        }

        if (!$definition->canPost()) {
            throw new AccessDeniedHttpException((string) $definition->message);
        }

        if (null !== $data->rating && !self::isRatingWithin($data->rating, $this->maxRating())) {
            throw new UnprocessableEntityHttpException(\sprintf('A rating must be between 0 and %d.', $this->maxRating()));
        }

        // One call per line: the event's setters are the module's own and return nothing.
        $event = new CommentCreateEvent();
        $event->setRef($ref);
        $event->setRefId($refId);
        $event->setTitle($data->title);
        $event->setContent($data->content);
        $event->setRating($data->rating);
        $event->setUsername($data->username);
        $event->setEmail($data->email);
        $event->setVerified($definition->isVerified());
        $event->setStatus($definition->isModerated() ? CommentModel::PENDING : CommentModel::ACCEPTED);
        $event->setLocale($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US');

        if (null !== $definition->customerId()) {
            $event->setCustomerId($definition->customerId());
            // The account names the author, never the request body: a customer cannot publish
            // under someone else's name.
            $event->setUsername($definition->customerDisplayName());
            $event->setEmail(null);
        }

        $this->eventDispatcher->dispatch($event, CommentEvents::COMMENT_CREATE);

        $comment = $event->getComment();

        if (!$comment instanceof CommentModel) {
            throw new UnprocessableEntityHttpException('The comment could not be saved.');
        }

        return $this->mapper->toResource($comment);
    }

    /**
     * A rating is optional, and when it is given it has to fit the shop's scale: the column
     * is a TINYINT and would take a 99 without a word.
     */
    public static function isRatingWithin(?int $rating, int $maxRating): bool
    {
        if (null === $rating) {
            return true;
        }

        return $rating >= 0 && $rating <= $maxRating;
    }

    private function maxRating(): int
    {
        return (int) ConfigQuery::read('comment_max_rating', (string) CommentModule::CONFIG_MAX_RATING);
    }

    private function customer(): ?Customer
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof Customer ? $user : null;
    }
}
