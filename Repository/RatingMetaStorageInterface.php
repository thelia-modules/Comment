<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Repository;

/**
 * Where the average rating and the number of ratings of an element are kept.
 *
 * They are read on every product page and on every product list, so they are stored rather
 * than aggregated at render time: a list of forty products would otherwise run forty
 * aggregations.
 */
interface RatingMetaStorageInterface
{
    public function store(string $ref, int $refId, float $average, int $count): void;

    /**
     * Removes both values. Called when an element has no rated comment left, so that no page
     * keeps showing an average computed from comments that are gone.
     */
    public function clear(string $ref, int $refId): void;
}
