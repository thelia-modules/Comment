<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Comment\Api\State\CommentPostProcessor;
use Comment\Api\State\CommentProvider;
use Comment\Form\CommentContentConstraints;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The comments of an element, for a front office that talks to the API rather than to Twig.
 *
 * Reading and writing are two different shapes on purpose. What comes out is what a shop
 * puts on a product page — an author's display name, their rating, the verified-purchase
 * mention — and it is built from accepted comments only. What goes in carries the two fields
 * an anonymous visitor fills, `username` and `email`, and neither is ever readable: the list
 * is public, and the e-mail address of whoever wrote a review is not.
 *
 * Neither operation is backed by the Propel bridge: the list has a filter that a client must
 * not be able to lift, and posting goes through the module's own event so that the rules of
 * the theme's form apply here too.
 */
#[ApiResource(
    shortName: 'Comment',
    operations: [
        new GetCollection(
            uriTemplate: '/front/comments',
            provider: CommentProvider::class,
            // Nothing to authenticate: an accepted comment is on the product page anyway.
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Get(
            uriTemplate: '/front/comments/{id}',
            provider: CommentProvider::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Post(
            uriTemplate: '/front/comments',
            // Whether a visitor may post at all is the `comment_only_customer` setting's
            // answer, not the firewall's: the processor asks the module.
            security: "is_granted('PUBLIC_ACCESS')",
            processor: CommentPostProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
)]
class Comment
{
    public const GROUP_FRONT_READ = 'front:comment:read';
    public const GROUP_FRONT_WRITE = 'front:comment:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public ?int $id = null;

    /** What the comment is attached to: `product` or `content`, as the shop allows. */
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    public ?string $ref = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    #[GreaterThan(0, groups: [self::GROUP_FRONT_WRITE])]
    public ?int $refId = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[Length(max: 255, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $title = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    #[NotBlank(normalizer: 'trim', groups: [self::GROUP_FRONT_WRITE])]
    #[Length(max: CommentContentConstraints::MAX_LENGTH, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $content = null;

    /** Bounded by the shop's own scale (`comment_max_rating`), which the processor reads. */
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?int $rating = null;

    /** The name the comment is published under. Never an e-mail address, never a full name. */
    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $author = null;

    /** Whether the author bought the element they are reviewing. */
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $verified = false;

    /**
     * Whether the comment is online. False for a comment that has just been posted on a
     * moderated shop, which is what tells the poster their comment is waiting.
     */
    #[Groups([self::GROUP_FRONT_READ])]
    public bool $published = false;

    #[Groups([self::GROUP_FRONT_READ])]
    public ?string $createdAt = null;

    /** What an anonymous visitor signs with. A signed-in customer's name comes from their account. */
    #[Groups([self::GROUP_FRONT_WRITE])]
    #[Length(max: 255, groups: [self::GROUP_FRONT_WRITE])]
    public ?string $username = null;

    #[Groups([self::GROUP_FRONT_WRITE])]
    #[Email(groups: [self::GROUP_FRONT_WRITE])]
    public ?string $email = null;
}
