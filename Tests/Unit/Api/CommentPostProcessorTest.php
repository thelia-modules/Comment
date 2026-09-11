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

namespace Comment\Tests\Unit\Api;

use ApiPlatform\Metadata\Post;
use Comment\Api\Resource\Comment as CommentResource;
use Comment\Api\State\CommentPostProcessor;
use Comment\Events\CommentCreateEvent;
use Comment\Events\CommentDefinitionEvent;
use Comment\Events\CommentEvents;
use Comment\Model\Comment as CommentModel;
use Comment\Service\Api\CommentPayloadMapper;
use Comment\Service\Front\CommentContentSanitizer;
use Comment\Service\Front\CommentDefinition;
use Comment\Service\Front\CommentDefinitionResolverInterface;
use Comment\Service\Front\CommentPostLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Posting a comment through the front API.
 *
 * The theme's form and this operation must not be two different doors with two different
 * rules: the rate limiter runs first, the module's own definition decides whether this
 * visitor may post at all, and the comment itself is created by the module's event rather
 * than written here.
 */
final class CommentPostProcessorTest extends TestCase
{
    public function testAFloodIsRefusedBeforeAnythingIsRead(): void
    {
        $dispatcher = $this->spyDispatcher();

        $processor = $this->processor(
            dispatcher: $dispatcher,
            definition: $this->accepted(),
            limiterAccepts: false,
        );

        try {
            $processor->process($this->input(), new Post());
            self::fail('A refused rate limit has to stop the request');
        } catch (TooManyRequestsHttpException) {
            self::assertSame([], $dispatcher->dispatched, 'Nothing must be dispatched once the limiter refused');
        }
    }

    public function testAVisitorTheModuleRefusesGetsAForbiddenAndNoComment(): void
    {
        $dispatcher = $this->spyDispatcher();

        $processor = $this->processor(
            dispatcher: $dispatcher,
            definition: new CommentDefinition(new CommentDefinitionEvent(), false, 'Only customer are allowed to publish comment'),
        );

        try {
            $processor->process($this->input(), new Post());
            self::fail('The module refused this visitor: the operation has to refuse too');
        } catch (AccessDeniedHttpException $exception) {
            self::assertSame('Only customer are allowed to publish comment', $exception->getMessage());
            self::assertSame([], $dispatcher->dispatched);
        }
    }

    public function testAnElementWithoutCommentsIsNotFound(): void
    {
        $processor = $this->processor(
            definition: new CommentDefinition(new CommentDefinitionEvent(), true, 'Comment not activated on this element.'),
        );

        $this->expectException(NotFoundHttpException::class);

        $processor->process($this->input(), new Post());
    }

    public function testAnAcceptedPostGoesThroughTheModulesOwnEvent(): void
    {
        $created = (new CommentModel())->setId(12)->setRef('product')->setRefId(4);
        $created->setStatus(CommentModel::PENDING)->setUsername('Someone')->setTitle('Solid')->setContent('Solid chair');

        $dispatcher = $this->spyDispatcher($created);

        $resource = $this->processor(dispatcher: $dispatcher, definition: $this->accepted())
            ->process($this->input(), new Post());

        self::assertCount(1, $dispatcher->dispatched);
        self::assertSame(CommentEvents::COMMENT_CREATE, $dispatcher->dispatched[0][0]);

        /** @var CommentCreateEvent $event */
        $event = $dispatcher->dispatched[0][1];

        self::assertSame('product', $event->getRef());
        self::assertSame(4, $event->getRefId());
        self::assertSame('Solid', $event->getTitle());
        self::assertSame('Solid chair', $event->getContent());
        self::assertSame('Someone', $event->getUsername());
        // Moderated by default: a posted comment is not published by the mere fact of being
        // posted.
        self::assertSame(CommentModel::PENDING, $event->getStatus());

        self::assertInstanceOf(CommentResource::class, $resource);
        self::assertSame(12, $resource->id);
        self::assertFalse($resource->published);
    }

    public function testARatingOutsideTheShopsScaleIsNotWithinIt(): void
    {
        self::assertTrue(CommentPostProcessor::isRatingWithin(null, 5), 'No rating at all is allowed');
        self::assertTrue(CommentPostProcessor::isRatingWithin(0, 5));
        self::assertTrue(CommentPostProcessor::isRatingWithin(5, 5));
        self::assertFalse(CommentPostProcessor::isRatingWithin(6, 5));
        self::assertFalse(CommentPostProcessor::isRatingWithin(-1, 5));
        // A shop whose scale is 10 accepts what a five-star shop refuses.
        self::assertTrue(CommentPostProcessor::isRatingWithin(6, 10));
    }

    private function input(): CommentResource
    {
        $resource = new CommentResource();
        $resource->ref = 'product';
        $resource->refId = 4;
        $resource->title = 'Solid';
        $resource->content = 'Solid chair';
        $resource->username = 'Someone';

        return $resource;
    }

    private function accepted(): CommentDefinition
    {
        return new CommentDefinition(new CommentDefinitionEvent(), false, null);
    }

    private function processor(
        ?object $dispatcher = null,
        ?CommentDefinition $definition = null,
        bool $limiterAccepts = true,
    ): CommentPostProcessor {
        $judge = $this->createStub(CommentDefinitionResolverInterface::class);
        $judge->method('resolve')->willReturn($definition ?? $this->accepted());

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $token = $this->createStub(TokenStorageInterface::class);

        return new CommentPostProcessor(
            $dispatcher ?? $this->spyDispatcher(),
            $judge,
            $this->limiter($limiterAccepts, $requestStack),
            new CommentPayloadMapper(new CommentContentSanitizer()),
            $token,
            $requestStack,
        );
    }

    private function limiter(bool $accepts, RequestStack $requestStack): CommentPostLimiter
    {
        $limit = new RateLimit($accepts ? 1 : 0, new \DateTimeImmutable(), $accepts, 10);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limit);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return new CommentPostLimiter($factory, $factory, $requestStack);
    }

    private function spyDispatcher(?CommentModel $created = null): object
    {
        return new class($created) implements EventDispatcherInterface {
            /** @var list<array{0: ?string, 1: object}> */
            public array $dispatched = [];

            public function __construct(private readonly ?CommentModel $created)
            {
            }

            public function dispatch(object $event, ?string $eventName = null): object
            {
                $this->dispatched[] = [$eventName, $event];

                if ($event instanceof CommentCreateEvent && null !== $this->created) {
                    $event->setComment($this->created);
                }

                return $event;
            }
        };
    }
}
