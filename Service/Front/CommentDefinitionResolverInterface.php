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

use Thelia\Model\Customer;

/**
 * Who may see the comments of an element, and who may add one.
 *
 * The theme's form, the LiveComponent and the front API all ask this before showing or
 * accepting anything, so a shop has one set of rules and not one per door.
 */
interface CommentDefinitionResolverInterface
{
    /**
     * @param Customer|null $customer the visitor to judge, when it does not come from the
     *                                session: an API request is stateless and carries its
     *                                customer in a token
     */
    public function resolve(string $ref, int $refId, ?Customer $customer = null): CommentDefinition;
}
