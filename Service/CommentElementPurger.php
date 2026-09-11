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

namespace Comment\Service;

use Comment\Repository\CommentRepository;
use Comment\Repository\CommentStorageInterface;
use Comment\Repository\RatingMetaRepository;
use Comment\Repository\RatingMetaStorageInterface;
use Comment\Service\Api\RatingMetaMemo;

/**
 * Everything the module holds about one element, removed when that element is.
 *
 * A comment says what it is about with a reference pair (`ref` / `ref_id`), and the average
 * it feeds lives under the same pair in `meta_data`. Neither can be a foreign key, so
 * deleting a product or a content takes nothing with it: the reviews stay, the stored average
 * stays, and the front API keeps serving both for something the shop no longer has.
 *
 * Deleting a customer is the opposite gesture and stays as it is: their comments are
 * anonymised, never removed, because a published review belongs to the shop's record.
 */
final readonly class CommentElementPurger
{
    public function __construct(
        private CommentStorageInterface $commentStorage,
        private RatingMetaStorageInterface $ratingMetaStorage,
    ) {
    }

    /**
     * Built by hand where no container is available.
     *
     * The API bridge instantiates a resource addon with `new` and injects nothing into it, so
     * the addon that reacts to a deleted product has to assemble this itself. Both
     * repositories take no dependency of their own, which is what makes that honest rather
     * than a service locator in disguise.
     */
    public static function standalone(): self
    {
        return new self(new CommentRepository(), new RatingMetaRepository());
    }

    /**
     * @return int how many comments were removed
     */
    public function purge(string $ref, int $refId): int
    {
        $deleted = $this->commentStorage->deleteByReference($ref, $refId);

        // Cleared even when there was no comment to delete: the two values are written by the
        // module and an element can carry them with every one of its comments already gone.
        $this->ratingMetaStorage->clear($ref, $refId);

        // The average may already have been read into the per-request memo by whatever is
        // rendering, and it now names something that does not exist.
        RatingMetaMemo::reset();

        return $deleted;
    }
}
