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

    /** @var array<string, array{average: float|null, count: int}> */
    private array $values = [];

    public function store(string $ref, int $refId, float $average, int $count): void
    {
        $this->stored[] = ['ref' => $ref, 'refId' => $refId, 'average' => $average, 'count' => $count];
        $this->values[$ref.'|'.$refId] = ['average' => $average, 'count' => $count];
    }

    public function clear(string $ref, int $refId): void
    {
        $this->cleared[] = ['ref' => $ref, 'refId' => $refId];
        unset($this->values[$ref.'|'.$refId]);
    }

    /**
     * What an element carries right now, in the shape the aggregate is read in.
     *
     * @return array{average: float|null, count: int}
     */
    public function read(string $ref, int $refId): array
    {
        return $this->values[$ref.'|'.$refId] ?? ['average' => null, 'count' => 0];
    }

    public function elementIdsRatedAtLeast(string $ref, float $minimumAverage, ?array $amongIds = null): array
    {
        $ids = [];

        foreach ($this->values as $key => $value) {
            [$storedRef, $refId] = explode('|', $key);
            $refId = (int) $refId;

            if ($storedRef !== $ref || $value['average'] === null || $value['average'] < $minimumAverage) {
                continue;
            }

            if ($amongIds !== null && !\in_array($refId, $amongIds, true)) {
                continue;
            }

            $ids[] = $refId;
        }

        return $ids;
    }
}
