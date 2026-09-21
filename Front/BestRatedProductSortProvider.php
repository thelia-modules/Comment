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

namespace Comment\Front;

use Comment\Api\Filter\ProductRatingOrderFilter;
use Comment\Comment as CommentModule;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Catalog\Product\ProductSortProviderInterface;

/**
 * Orders a product listing from the best rated product to the worst.
 *
 * The other half of this order is Comment\Api\Filter\ProductRatingOrderFilter, which the module
 * appends to the public product collection: the parameter below is the one that filter answers.
 *
 * Nothing here asks whether the module is installed. Only an activated module reaches the
 * container, so the entry exists exactly where the data behind it does.
 *
 * Products nobody rated go last whatever the direction; the filter decides that, not the theme.
 */
final readonly class BestRatedProductSortProvider implements ProductSortProviderInterface
{
    public function __construct(
        // Thelia's own Translator: the only one carrying the module catalogues. Twig's |trans
        // goes to the Symfony translator, which on the front office knows the theme catalogue
        // only, so the label is translated here rather than left to the theme.
        private TranslatorInterface $translator,
    ) {
    }

    public function value(): string
    {
        return 'best_rated';
    }

    public function title(): string
    {
        return $this->translator->trans('Best rated', [], CommentModule::MESSAGE_DOMAIN);
    }

    /**
     * Right after the orders on price, before the ones on date.
     */
    public function position(): int
    {
        return 30;
    }

    public function parameters(): array
    {
        return [\sprintf('order[%s]', ProductRatingOrderFilter::ORDER_PROPERTY) => 'desc'];
    }
}
