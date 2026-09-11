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

namespace Comment\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * The two descriptions of the comment table have to say the same thing.
 *
 * Config/schema.xml is what Propel builds the models from; Config/thelia.sql is what a fresh
 * activation actually runs. A shop installed today gets the SQL file, so an index declared in
 * one and missing from the other is an index half the shops do not have.
 */
final class SchemaTest extends TestCase
{
    private string $schema;

    private string $sql;

    protected function setUp(): void
    {
        $config = \dirname(__DIR__, 3).'/Config';

        $this->schema = (string) file_get_contents($config.'/schema.xml');
        $this->sql = (string) file_get_contents($config.'/thelia.sql');
    }

    /**
     * The product page reads the comments of one element: ref and ref_id together, which is
     * the only filter that query has.
     */
    public function testTheReferenceIsIndexed(): void
    {
        self::assertMatchesRegularExpression(
            '#<index name="idx_comment_ref">\s*<index-column name="ref"\s*/>\s*<index-column name="ref_id"\s*/>\s*</index>#',
            $this->schema
        );
        self::assertStringContainsString('INDEX `idx_comment_ref` (`ref`, `ref_id`)', $this->sql);
    }

    /**
     * The moderation list and the four status counters filter on the status alone.
     */
    public function testTheStatusIsIndexed(): void
    {
        self::assertMatchesRegularExpression(
            '#<index name="idx_comment_status">\s*<index-column name="status"\s*/>\s*</index>#',
            $this->schema
        );
        self::assertStringContainsString('INDEX `idx_comment_status` (`status`)', $this->sql);
    }
}
