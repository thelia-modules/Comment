<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Tests\Double;

use Comment\Repository\RatingMetaStorageInterface;

/**
 * Records what the module asked to store or to clear for an element's rating.
 */
final class InMemoryRatingMetaStorage implements RatingMetaStorageInterface
{
    /** @var list<array{ref: string, refId: int, average: float, count: int}> */
    public array $stored = [];

    /** @var list<array{ref: string, refId: int}> */
    public array $cleared = [];

    public function store(string $ref, int $refId, float $average, int $count): void
    {
        $this->stored[] = ['ref' => $ref, 'refId' => $refId, 'average' => $average, 'count' => $count];
    }

    public function clear(string $ref, int $refId): void
    {
        $this->cleared[] = ['ref' => $ref, 'refId' => $refId];
    }
}
