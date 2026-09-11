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

namespace Comment\Tests\Unit\Install;

use Comment\Install\CommentSchemaUpgrade;
use PHPUnit\Framework\TestCase;

/**
 * What a database installed before these indexes existed has to be given.
 *
 * Config/thelia.sql only runs on a first activation, so an existing shop never sees a change
 * made to it. The statements are decided from what the database already has, which is what
 * makes the upgrade safe to run again: MySQL and MariaDB have no ADD INDEX IF NOT EXISTS, and
 * Thelia's Database::insertSql() turns any SQL error into a rolled back module update.
 */
final class CommentSchemaUpgradeTest extends TestCase
{
    public function testADatabaseMissingBothIndexesGetsBoth(): void
    {
        $statements = CommentSchemaUpgrade::statementsFor(['idx_comment_user_id'], 'SET NULL');

        self::assertSame(
            [
                'ALTER TABLE `comment` ADD INDEX `idx_comment_ref` (`ref`, `ref_id`)',
                'ALTER TABLE `comment` ADD INDEX `idx_comment_status` (`status`)',
            ],
            $statements
        );
    }

    public function testAnIndexAlreadyThereIsNotAddedAgain(): void
    {
        $statements = CommentSchemaUpgrade::statementsFor(['idx_comment_user_id', 'idx_comment_ref'], 'SET NULL');

        self::assertSame(
            ['ALTER TABLE `comment` ADD INDEX `idx_comment_status` (`status`)'],
            $statements
        );
    }

    public function testAnUpToDateDatabaseIsLeftAlone(): void
    {
        $statements = CommentSchemaUpgrade::statementsFor(
            ['idx_comment_user_id', 'idx_comment_ref', 'idx_comment_status'],
            'SET NULL'
        );

        self::assertSame([], $statements, 'the upgrade is not replayable');
    }

    /**
     * A cascade would take a customer's comments away with their account, refused and
     * reported ones included, and leave every average they weighed in untouched.
     */
    public function testACascadingForeignKeyIsReplacedBySetNull(): void
    {
        $statements = CommentSchemaUpgrade::statementsFor(
            ['idx_comment_user_id', 'idx_comment_ref', 'idx_comment_status'],
            'CASCADE'
        );

        self::assertSame(
            [
                'ALTER TABLE `comment` DROP FOREIGN KEY `fk_comment_customer_id`',
                'ALTER TABLE `comment` ADD CONSTRAINT `fk_comment_customer_id`'
                .' FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`)'
                .' ON UPDATE RESTRICT ON DELETE SET NULL',
            ],
            $statements
        );
    }

    public function testAMissingForeignKeyIsAddedWithoutBeingDroppedFirst(): void
    {
        $statements = CommentSchemaUpgrade::statementsFor(
            ['idx_comment_user_id', 'idx_comment_ref', 'idx_comment_status'],
            null
        );

        self::assertCount(1, $statements);
        self::assertStringStartsWith('ALTER TABLE `comment` ADD CONSTRAINT', $statements[0]);
    }
}
