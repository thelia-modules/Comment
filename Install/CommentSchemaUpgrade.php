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

    public const CUSTOMER_FOREIGN_KEY = 'fk_comment_customer_id';

    private const TABLE = 'comment';

    /**
     * @param list<string> $existingIndexes    the index names the table already carries
     * @param string|null  $customerDeleteRule the ON DELETE rule of the customer foreign key,
     *                                         null when the table carries no such key
     *
     * @return list<string>
     */
    public static function statementsFor(array $existingIndexes, ?string $customerDeleteRule): array
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

        // Deleting a customer must not delete what they wrote: a refused or a reported comment
        // is the trace of a moderation decision, and a row that disappears on its own leaves
        // every average it weighed in as it was. The account is anonymized instead, by
        // Comment\Service\Customer\CommentPersonalDataProvider.
        if ('SET NULL' !== strtoupper((string) $customerDeleteRule)) {
            if (null !== $customerDeleteRule) {
                $statements[] = 'ALTER TABLE `comment` DROP FOREIGN KEY `'.self::CUSTOMER_FOREIGN_KEY.'`';
            }

            $statements[] = 'ALTER TABLE `comment` ADD CONSTRAINT `'.self::CUSTOMER_FOREIGN_KEY.'`'
                .' FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`)'
                .' ON UPDATE RESTRICT ON DELETE SET NULL';
        }

        return $statements;
    }

    public function apply(?ConnectionInterface $connection = null): void
    {
        $connection ??= Propel::getConnection('TheliaMain');

        $statements = self::statementsFor(
            $this->existingIndexes($connection),
            $this->customerDeleteRule($connection)
        );

        foreach ($statements as $statement) {
            $connection->exec($statement);
        }
    }

    private function customerDeleteRule(ConnectionInterface $connection): ?string
    {
        $rule = $connection
            ->query(
                'SELECT DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS'
                ." WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '".self::TABLE."'"
                ." AND CONSTRAINT_NAME = '".self::CUSTOMER_FOREIGN_KEY."'"
            )
            ->fetchColumn();

        return false === $rule || null === $rule ? null : (string) $rule;
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
