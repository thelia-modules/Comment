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

namespace Comment\Service\BackOffice;

use Comment\Events\CommentEvents;
use Comment\Events\CommentReferenceGetterEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Resolves what a comment is attached to (a product, a content, anything a module answers
 * COMMENT_REFERENCE_GETTER for): title, type title, edit and view URLs.
 *
 * Same dispatch the deleted CommentLoop did through load_ref, memoized per ref and locale so
 * a list of twenty comments on one product resolves it once.
 */
final class CommentReferenceResolver
{
    /** @var array<string, array{object: mixed, title: ?string, typeTitle: ?string, editUrl: ?string, viewUrl: ?string}> */
    private array $cache = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array{object: mixed, title: ?string, typeTitle: ?string, editUrl: ?string, viewUrl: ?string}
     */
    public function resolve(?string $ref, ?int $refId, string $locale): array
    {
        if (null === $ref || null === $refId) {
            return $this->emptyReference();
        }

        $key = $ref.':'.$refId.':'.$locale;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $event = new CommentReferenceGetterEvent($ref, $refId, $locale);
        $this->eventDispatcher->dispatch($event, CommentEvents::COMMENT_REFERENCE_GETTER);

        return $this->cache[$key] = [
            'object' => $event->getObject(),
            'title' => $event->getTitle(),
            'typeTitle' => $event->getTypeTitle(),
            'editUrl' => $event->getEditUrl(),
            'viewUrl' => $event->getViewUrl(),
        ];
    }

    /**
     * @return array{object: mixed, title: ?string, typeTitle: ?string, editUrl: ?string, viewUrl: ?string}
     */
    private function emptyReference(): array
    {
        return ['object' => null, 'title' => null, 'typeTitle' => null, 'editUrl' => null, 'viewUrl' => null];
    }
}
