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

use Comment\Model\Comment;

/**
 * What a moderation decision reads like in admin_log.
 *
 * Accepting a comment publishes it on the shop and refusing one takes it down, so both belong
 * in the administration log next to the rest. The wording stays in English and names the
 * status code rather than its translated label: a trail read months later must not depend on
 * the locale of whoever was looking at the screen.
 */
final class CommentModerationLog
{
    private const STATUS_NAMES = [
        Comment::PENDING => 'pending',
        Comment::ACCEPTED => 'accepted',
        Comment::REFUSED => 'refused',
        Comment::ABUSED => 'abused',
    ];

    public static function statusChangeMessage(int $commentId, int $newStatus): string
    {
        return \sprintf(
            'Comment %d set to %s',
            $commentId,
            self::STATUS_NAMES[$newStatus] ?? 'unknown status '.$newStatus
        );
    }

    /**
     * @param string $value the value written to the element's activation meta: "1", "0", or
     *                      anything else to mean the element follows the shop setting again
     */
    public static function activationMessage(string $ref, int $refId, string $value): string
    {
        return match ($value) {
            '1' => \sprintf('Comments enabled on %s %d', $ref, $refId),
            '0' => \sprintf('Comments disabled on %s %d', $ref, $refId),
            default => \sprintf('Comments on %s %d back to the shop setting', $ref, $refId),
        };
    }
}
