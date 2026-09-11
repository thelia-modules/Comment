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

use Comment\Comment;
use Comment\Events\CommentDefinitionEvent;
use Comment\Events\CommentEvents;
use Comment\Exception\InvalidDefinitionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;

/**
 * Asks the module whether comments may be shown and posted on a given element.
 *
 * The rules live in Comment\Action\CommentAction (allowed references, global and per-element
 * activation, customer-only, verified-purchase-only). They are reached by dispatching
 * COMMENT_GET_DEFINITION, which throws InvalidDefinitionException when the element is out:
 * a silent exception means "render nothing", a loud one means "render the reason".
 *
 * Both the theme hook and the LiveComponent go through this resolver, so the front never
 * shows a form the module would refuse.
 */
final readonly class CommentDefinitionResolver implements CommentDefinitionResolverInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private SecurityContext $securityContext,
    ) {
    }

    public function resolve(string $ref, int $refId, ?Customer $customer = null): CommentDefinition
    {
        $event = new CommentDefinitionEvent();
        $event
            ->setRef($ref)
            ->setRefId($refId)
            // The session holds the customer on the front office; an API request is stateless
            // and hands the one its token authenticated.
            ->setCustomer($customer ?? $this->securityContext->getCustomerUser())
            ->setConfig(Comment::getConfig());

        try {
            $this->eventDispatcher->dispatch($event, CommentEvents::COMMENT_GET_DEFINITION);
        } catch (InvalidDefinitionException $exception) {
            $event->setValid(false);

            return new CommentDefinition($event, $exception->isSilent(), $exception->getMessage());
        }

        $event->setValid(true);

        return new CommentDefinition($event, false, null);
    }
}
