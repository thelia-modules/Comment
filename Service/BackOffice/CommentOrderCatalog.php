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

namespace Comment\Service\BackOffice;

use Comment\Repository\CommentRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The orders a moderator may sort the comment list by, labelled.
 *
 * "Most reported first" is the one that makes reporting worth something: a comment visitors
 * flagged has to reach the top of the queue without anything having hidden it.
 */
final readonly class CommentOrderCatalog
{
    public const TRANSLATION_DOMAIN = 'comment.bo.default';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Keyed by the value that travels in the query string, in the order they are offered.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $labels = [
            'created_reverse' => 'Most recent first',
            'created' => 'Oldest first',
            'abuse_reverse' => 'Most reported first',
            'abuse' => 'Least reported first',
            'rating_reverse' => 'Highest rating first',
            'rating' => 'Lowest rating first',
            'status' => 'By status',
        ];

        // The repository is what knows which orders exist: an entry here that it cannot sort
        // by would silently fall back on the most recent.
        $catalog = [];

        foreach (CommentRepository::ORDERS as $order) {
            $catalog[$order] = $this->translator->trans(
                $labels[$order] ?? $order,
                [],
                self::TRANSLATION_DOMAIN
            );
        }

        return $catalog;
    }
}
