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

namespace Comment\Service\Api;

/**
 * What an element's accepted comments amount to: an average and a number of ratings.
 *
 * The average is nullable and the count is not, and that asymmetry is the contract the
 * front reads: an element nobody has rated has no average at all, where a zero would be
 * read as a rating of zero out of five.
 */
final readonly class RatingSnapshot
{
    public function __construct(
        public ?float $average,
        public int $count,
    ) {
    }

    public static function none(): self
    {
        return new self(null, 0);
    }
}
