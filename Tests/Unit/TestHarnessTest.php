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

namespace Comment\Tests\Unit;

use Comment\Model\Comment;
use PHPUnit\Framework\TestCase;

/**
 * Guards the harness the other tests stand on.
 *
 * The Propel base class is a stand-in (Tests/Double/PropelBase/Comment.php) because the real
 * one is built into var/propel/<env>/model at activation time. A column added to
 * Config/schema.xml and forgotten there would make every other test pass against a model the
 * shop does not have, so the two are compared here.
 */
final class TestHarnessTest extends TestCase
{
    public function testTheModuleClassesAreAutoloaded(): void
    {
        self::assertSame(0, Comment::PENDING);
        self::assertSame(1, Comment::ACCEPTED);
        self::assertSame(2, Comment::REFUSED);
        self::assertSame(3, Comment::ABUSED);
    }

    public function testTheStandInCarriesEveryColumnOfTheSchema(): void
    {
        $schema = simplexml_load_file(\dirname(__DIR__, 2).'/Config/schema.xml');

        self::assertNotFalse($schema, 'Config/schema.xml is not readable');

        $columns = [];

        foreach ($schema->xpath('//table[@name="comment"]/column') as $column) {
            $columns[] = (string) $column['name'];
        }

        self::assertNotEmpty($columns);

        $comment = new Comment();

        foreach ($columns as $column) {
            $accessor = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $column)));

            self::assertTrue(
                method_exists($comment, $accessor),
                \sprintf('Column "%s" of Config/schema.xml has no %s() on the model', $column, $accessor)
            );
        }
    }

    public function testTheStandInRecordsSavesInsteadOfWritingThem(): void
    {
        $comment = new Comment();

        self::assertSame(0, $comment->saveCount);

        $comment->setRef('product')->setRefId(11)->save();

        self::assertSame(1, $comment->saveCount);
        self::assertFalse($comment->isNew());
    }
}
