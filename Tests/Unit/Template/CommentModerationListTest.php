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

namespace Comment\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * What the moderation list offers a moderator.
 *
 * A report raises a counter, and a counter nobody can sort on is a counter nobody reads: the
 * list has to offer the order, hand it to the repository, and show the number next to the
 * comment it belongs to. The repository side is covered by CommentOrderTest; this is the
 * surface, which is a template and has no unit to call.
 */
final class CommentModerationListTest extends TestCase
{
    private string $page;

    private string $table;

    private string $presenter;

    protected function setUp(): void
    {
        $module = \dirname(__DIR__, 3);
        $templates = $module.'/templates/backOffice/default-twig';

        $this->page = (string) file_get_contents($templates.'/comments.html.twig');
        $this->table = (string) file_get_contents($templates.'/Comment/_comments_table.html.twig');
        $this->presenter = (string) file_get_contents($module.'/Service/BackOffice/CommentListPresenter.php');
    }

    public function testTheListOffersTheOrdersTheCatalogCarries(): void
    {
        self::assertStringContainsString('name="loop_order"', $this->page);
        self::assertStringContainsString('for order, label in orders', $this->page);
    }

    /**
     * The select is inside the filter form, so choosing an order does not drop the status
     * being filtered on, and the pager carries it: page 2 of "most reported first" is still
     * sorted by reports.
     */
    public function testTheOrderTravelsWithTheFilterAndThePager(): void
    {
        self::assertStringContainsString('loop_order', $this->page);
        self::assertStringContainsString('filters.toQueryParams()', $this->table);
    }

    public function testThePresenterHandsTheOrdersToTheTemplate(): void
    {
        self::assertStringContainsString('CommentOrderCatalog', $this->presenter);
        self::assertStringContainsString("'orders' => \$this->orderCatalog->all()", $this->presenter);
    }

    /**
     * Sorting on a number the page does not show leaves a moderator guessing why a comment is
     * at the top.
     */
    public function testTheNumberOfReportsIsShownOnTheRowItBelongsTo(): void
    {
        self::assertStringContainsString('row.abuse', $this->table);
    }
}
