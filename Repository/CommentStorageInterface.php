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

namespace Comment\Repository;

use Comment\Model\Comment;

/**
 * The reads and writes the module's listeners need, behind a contract.
 *
 * Comment\Action\CommentAction used to reach for CommentQuery and Comment::save() itself,
 * which made its rules impossible to exercise without a built Propel model tree and a
 * database. They are the rules a shop's data depends on, so they are reached through this
 * interface instead and CommentRepository is the one implementation.
 */
interface CommentStorageInterface
{
    public function findById(int $id): ?Comment;

    public function save(Comment $comment): void;
}
