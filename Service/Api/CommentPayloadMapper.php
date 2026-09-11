<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Service\Api;

use Comment\Api\Resource\Comment as CommentResource;
use Comment\Model\Comment as CommentModel;
use Comment\Service\Front\CommentContentSanitizer;

/**
 * A stored comment, as the front API hands it over.
 *
 * The row carries more than a visitor may see — the moderation status, the number of abuse
 * reports, and the e-mail address an anonymous author typed. The resource is built field by
 * field rather than mapped column by column, so a column added to the table later cannot
 * turn up in a public payload by itself.
 *
 * The text goes out through the same sanitizer the theme's block uses: a moderator judges
 * what was typed, a shop shows text and nothing else.
 */
final readonly class CommentPayloadMapper
{
    public function __construct(
        private CommentContentSanitizer $sanitizer,
    ) {
    }

    public function toResource(CommentModel $comment): CommentResource
    {
        $resource = new CommentResource();

        $resource->id = $comment->getId();
        $resource->ref = $comment->getRef();
        $resource->refId = $comment->getRefId();
        $resource->title = $this->sanitizer->sanitize($comment->getTitle());
        $resource->content = $this->sanitizer->sanitize($comment->getContent());
        $resource->rating = $comment->getRating();
        $resource->author = $this->sanitizer->sanitize($comment->getUsername());
        // TINYINT: the getter answers an int, and comparing it to true never holds.
        $resource->verified = 1 === (int) $comment->getVerified();
        $resource->published = CommentModel::ACCEPTED === $comment->getStatus();
        $resource->createdAt = $this->createdAt($comment);

        return $resource;
    }

    private function createdAt(CommentModel $comment): ?string
    {
        $createdAt = $comment->getCreatedAt();

        if ($createdAt instanceof \DateTimeInterface) {
            return $createdAt->format(\DateTimeInterface::ATOM);
        }

        return null === $createdAt || '' === $createdAt ? null : (string) $createdAt;
    }
}
