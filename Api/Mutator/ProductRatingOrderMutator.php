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

namespace Comment\Api\Mutator;

use ApiPlatform\Metadata\AsOperationMutator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\OperationMutatorInterface;
use Comment\Api\Filter\ProductRatingOrderFilter;

/**
 * Offers "order[rating]" on the public product collection.
 *
 * Thelia\Api\Resource\Product declares its filters with #[ApiFilter] attributes, which only the
 * core can edit, and the core has no business knowing this module exists. An operation mutator
 * is the seam that lets an installed module append a filter to a resource it does not own:
 * Thelia\Api\Bridge\Propel\Extension\FilterExtension resolves whatever service ids the operation
 * carries, so appending one here is enough.
 *
 * The collection operation alone: sorting an item answers nothing.
 */
#[AsOperationMutator(self::OPERATION_NAME)]
final class ProductRatingOrderMutator implements OperationMutatorInterface
{
    /**
     * As API Platform names it once the resource metadata is built. Read from the compiled
     * container, not guessed: a name that matches nothing fails silently, leaving the sort
     * parameter ignored and the listing in the merchant's own order.
     */
    public const OPERATION_NAME = '_api_/front/products_get_collection';

    public function __invoke(Operation $operation): Operation
    {
        $filters = $operation->getFilters() ?? [];

        if (\in_array(ProductRatingOrderFilter::class, $filters, true)) {
            return $operation;
        }

        $filters[] = ProductRatingOrderFilter::class;

        return $operation->withFilters($filters);
    }
}
