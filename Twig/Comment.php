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

namespace Comment\Twig;

use Comment\Comment as CommentModule;
use Comment\Events\CommentCreateEvent;
use Comment\Events\CommentEvents;
use Comment\Form\AddCommentForm;
use Comment\Model\Comment as CommentModel;
use Comment\Repository\CommentRepository;
use Comment\Service\Front\CommentDefinition;
use Comment\Service\Front\CommentContentSanitizer;
use Comment\Service\Front\CommentDefinitionResolver;
use Comment\Service\Front\CommentPostLimiter;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MetaDataQuery;

/**
 * The comment block of an element: the comments already accepted, and the form to add one.
 *
 * Posting goes through the module's own CommentCreateEvent, so the rules (moderation,
 * verified purchase, rating) stay in Comment\Action\CommentAction and are shared with the
 * back-office.
 */
#[AsLiveComponent(name: 'Comment', template: '@CommentModule/components/Comment.html.twig')]
class Comment
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** How many comments a visitor is given at first, and how many each "show more" adds. */
    private const PAGE_SIZE = 10;

    /**
     * The most a single render will ever read. `shown` grows by PAGE_SIZE per action and is
     * signed with the rest of the props, but a replayed action still costs a query: this is
     * what keeps that query bounded.
     */
    private const MAX_SHOWN = 200;

    private const FRONT_TRANSLATION_DOMAIN = 'comment.fo.default';

    #[LiveProp]
    public string $ref = 'product';

    #[LiveProp]
    public int $refId = 0;

    /** Set after a successful post: a form error would be lost, the props are rehydrated. */
    #[LiveProp]
    public ?string $feedback = null;

    #[LiveProp]
    public ?string $error = null;

    /** How many comments the visitor asked to see. Grows by PAGE_SIZE on each showMore(). */
    #[LiveProp]
    public int $shown = self::PAGE_SIZE;

    private ?CommentDefinition $definition = null;

    /**
     * One search per render: getComments(), getTotal() and hasMore() all read it.
     *
     * @var array{items: list<CommentModel>, total: int}|null
     */
    private ?array $searchResult = null;

    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CommentDefinitionResolver $definitionResolver,
        private readonly CommentRepository $commentRepository,
        private readonly CommentPostLimiter $postLimiter,
        private readonly CommentContentSanitizer $sanitizer,
        private readonly RequestStack $requestStack,
        // Thelia's own Translator: the only one carrying the module catalogues. Twig's |trans
        // goes to the Symfony translator, which on the front office knows the theme catalogue
        // only, so every module string is translated here and exposed to the template.
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Front-office labels, from I18n/frontOffice/default.
     *
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return [
            'title' => $this->translateFront('Comments'),
            'empty' => $this->translateFront('There are no comments yet'),
            'verified' => $this->translateFront('Verified'),
            'rating' => $this->translateFront('rating'),
            'ratings' => $this->translateFront('ratings'),
            'add' => $this->translateFront('Add your comment'),
            'send' => $this->translateFront('Send'),
            'total' => $this->translateFront('Customers have rated this product'),
            'more' => $this->translateFront('Load more comments...'),
        ];
    }

    public function getDefinition(): CommentDefinition
    {
        return $this->definition ??= $this->definitionResolver->resolve($this->ref, $this->refId);
    }

    /**
     * The comments a visitor may read: the accepted ones, most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function getComments(): array
    {
        return array_map(
            fn (CommentModel $comment): array => [
                'id' => $comment->getId(),
                // What a visitor typed, cleaned on its way out: the moderator sees the stored
                // text, the shop shows text and nothing else.
                'author' => $this->sanitizer->sanitize($comment->getUsername()),
                'title' => $this->sanitizer->sanitize($comment->getTitle()),
                'content' => $this->sanitizer->sanitize($comment->getContent()),
                'rating' => $comment->getRating(),
                'verified' => (bool) $comment->getVerified(),
                'date' => $comment->getCreatedAt(),
            ],
            $this->search()['items'],
        );
    }

    public function getTotal(): int
    {
        return $this->search()['total'];
    }

    /**
     * Whether there is anything left to show. False once the ceiling is reached, so the button
     * never invites a click that would add nothing.
     */
    public function hasMore(): bool
    {
        return \count($this->search()['items']) < min($this->getTotal(), self::MAX_SHOWN);
    }

    #[LiveAction]
    public function showMore(): void
    {
        // Nothing is read here: the search runs while rendering, after `shown` has grown, so
        // there is no result cached from before the increment.
        $this->shown += self::PAGE_SIZE;
    }

    /**
     * @return array{items: list<CommentModel>, total: int}
     */
    private function search(): array
    {
        return $this->searchResult ??= $this->commentRepository->search(
            ref: $this->ref,
            refId: $this->refId,
            status: CommentModel::ACCEPTED,
            limit: max(self::PAGE_SIZE, min($this->shown, self::MAX_SHOWN)),
        );
    }

    /**
     * Average rating, as recomputed by the module every time a comment is accepted or removed.
     */
    public function getAverageRating(): ?float
    {
        $rating = MetaDataQuery::getVal(CommentModel::META_KEY_RATING, $this->ref, $this->refId);

        return null === $rating ? null : (float) $rating;
    }

    /**
     * How many accepted comments carry a rating, stored alongside the average.
     *
     * Read rather than counted: a page listing forty products asks forty times, and the count
     * shown next to the average has to be the number of ratings, not the number of comments.
     */
    public function getRatingCount(): ?int
    {
        $count = MetaDataQuery::getVal(CommentModel::META_KEY_RATING_COUNT, $this->ref, $this->refId);

        return null === $count ? null : (int) $count;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->error = null;
        $this->feedback = null;

        // First, before any query: a replayed action has to cost as little as possible.
        if (!$this->postLimiter->allows($this->ref, $this->refId)) {
            $this->error = $this->translator->trans(
                'Too many comments have been sent from here. Please try again later.',
                [],
                CommentModule::MESSAGE_DOMAIN
            );

            return;
        }

        $definition = $this->getDefinition();

        if (!$definition->canPost()) {
            $this->error = $definition->message;

            return;
        }

        // Throws when the form is invalid, which re-renders the component with its errors.
        $this->submitForm();

        $form = $this->getForm();
        $event = new CommentCreateEvent();

        if ($form instanceof Form) {
            $event->bindForm($form);
        }

        $event->setRef($this->ref);
        $event->setRefId($this->refId);
        $event->setVerified($definition->isVerified());
        $event->setStatus($definition->isModerated() ? CommentModel::PENDING : CommentModel::ACCEPTED);
        $event->setLocale($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US');

        if (null !== $definition->customerId()) {
            $event->setCustomerId($definition->customerId());
            // The username and email fields are not rendered for a signed-in customer, so the
            // form submits nothing for them: the author has to come from the account, and it
            // overwrites whatever bindForm() left behind.
            $event->setUsername($definition->customerDisplayName());
        }

        $this->eventDispatcher->dispatch($event, CommentEvents::COMMENT_CREATE);

        if (null === $event->getComment()) {
            $this->error = $this->translator->trans(
                'Sorry, an unknown error occurred. Please try again.',
                [],
                CommentModule::MESSAGE_DOMAIN
            );

            return;
        }

        $this->feedback = $definition->isModerated()
            ? $this->translator->trans(
                'Your comment will be put online once verified.',
                [],
                CommentModule::MESSAGE_DOMAIN
            )
            : $this->translator->trans(
                'Thank you for submitting your comment.',
                [],
                CommentModule::MESSAGE_DOMAIN
            );

        $this->resetForm();
        $this->searchResult = null;
    }

    private function translateFront(string $id): string
    {
        return $this->translator->trans($id, [], self::FRONT_TRANSLATION_DOMAIN);
    }

    protected function instantiateForm(): FormInterface
    {
        $definition = $this->getDefinition();

        $validationGroups = ['Default'];

        if (null === $definition->customerId()) {
            $validationGroups[] = 'anonymous';
        }

        // The 0..5 range constraints of AddCommentForm carry the 'rating' group: they belong in
        // the groups when the field is shown, not when it is hidden.
        if ($definition->hasRating()) {
            $validationGroups[] = 'rating';
        }

        return $this->formFactory
            ->createForm(
                AddCommentForm::getName(),
                data: ['ref' => $this->ref, 'ref_id' => $this->refId],
                options: ['validation_groups' => $validationGroups],
            )
            ->getForm();
    }

    public function getMaxRating()
    {
        return ConfigQuery::read('comment_max_rating', 0);
    }
}
