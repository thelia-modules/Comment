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

use Comment\Form\Field\RatingType;
use Comment\Repository\RatingMetaStorageInterface;
use Thelia\Domain\Catalog\Product\ProductRatingSourceInterface;
use Thelia\Model\ConfigQuery;

/**
 * Feeds the rating facet of the product listings ("4 stars & up" and so on) with the ratings
 * the module collects.
 *
 * It compares the stored average of the accepted comments, the figure the product card shows
 * and the best-rated order sorts on. The facet counts on five stars while a shop can rate on
 * another scale (`comment_max_rating`): the threshold is brought to that scale before reading.
 *
 * Like the best-rated order, nothing here asks whether the module is installed: only an
 * activated module reaches the container, so the facet shows exactly where reviews exist.
 * On a core without ProductRatingSourceInterface, the module does not register this class.
 */
final readonly class RatingFacetSource implements ProductRatingSourceInterface
{
    private const FACET_SCALE = 5;

    // MetaData::PRODUCT_KEY, written out as ElementDeletionListener does: the generated base
    // models are not loadable by the module's unit tests, which run without a kernel.
    private const PRODUCT_REF = 'product';

    public function __construct(
        private RatingMetaStorageInterface $ratings,
        // The scale the shop rates on; read from the configuration when left out.
        private ?int $ratingScale = null,
    ) {
    }

    public function productIdsRatedAtLeast(float $minimumRating, ?array $amongProductIds = null): array
    {
        $scale = $this->ratingScale ?? RatingType::scaleFrom(ConfigQuery::read('comment_max_rating'));

        return $this->ratings->elementIdsRatedAtLeast(
            self::PRODUCT_REF,
            $minimumRating * $scale / self::FACET_SCALE,
            $amongProductIds,
        );
    }
}
