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

use Comment\Events\CommentDefinitionEvent;

/**
 * What the module answered for one element: may comments be displayed, may this visitor post,
 * and if not, whether the reason is worth showing.
 */
final readonly class CommentDefinition
{
    public function __construct(
        public CommentDefinitionEvent $event,
        public bool $hidden,
        public ?string $message,
    ) {
    }

    public function canPost(): bool
    {
        return null === $this->message;
    }

    public function hasRating(): bool
    {
        return (bool) $this->event->hasRating();
    }

    public function isVerified(): bool
    {
        return (bool) $this->event->isVerified();
    }

    public function isModerated(): bool
    {
        return (bool) ($this->event->getConfig()['moderate'] ?? true);
    }

    public function customerId(): ?int
    {
        return $this->event->getCustomer()?->getId();
    }
}
