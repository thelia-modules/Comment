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

namespace Comment\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Brings the comment table of an already installed shop up to Config/schema.xml.
 *
 * Config/thelia.sql only runs on a first activation, so a change made to it never reaches a
 * database that is already there. A Config/update/<version>.sql file would, but only once and
 * only on a version bump: Thelia's Database::insertSql() raises on any SQL error and
 * ModuleManagement rolls the whole module update back, and neither MySQL nor MariaDB has
 * ADD INDEX IF NOT EXISTS or ADD CONSTRAINT IF NOT EXISTS to make such a file replayable.
 *
 * So the statements are decided from what the database already carries. Running this twice
 * does nothing the second time, which is what a fresh install needs as well: thelia.sql has
 * already put everything in place there.
 */
final class CommentSchemaUpgrade
{
    public const INDEX_REFERENCE = 'idx_comment_ref';

    public const INDEX_STATUS = 'idx_comment_status';

    private const TABLE = 'comment';

    /**
     * @param list<string> $existingIndexes the index names the table already carries
     *
     * @return list<string>
     */
    public static function statementsFor(array $existingIndexes): array
    {
        $statements = [];

        // The product page filters on both columns together, and nothing else.
        if (!\in_array(self::INDEX_REFERENCE, $existingIndexes, true)) {
            $statements[] = 'ALTER TABLE `comment` ADD INDEX `'.self::INDEX_REFERENCE.'` (`ref`, `ref_id`)';
        }

        // The moderation list and the status counters filter on the status alone.
        if (!\in_array(self::INDEX_STATUS, $existingIndexes, true)) {
            $statements[] = 'ALTER TABLE `comment` ADD INDEX `'.self::INDEX_STATUS.'` (`status`)';
        }

        return $statements;
    }

    public function apply(?ConnectionInterface $connection = null): void
    {
        $connection ??= Propel::getConnection('TheliaMain');

        foreach (self::statementsFor($this->existingIndexes($connection)) as $statement) {
            $connection->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function existingIndexes(ConnectionInterface $connection): array
    {
        $rows = $connection
            ->query(
                'SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS'
                ." WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".self::TABLE."'"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        return array_values(array_map(static fn (array $row): string => (string) $row['INDEX_NAME'], $rows));
    }
}
