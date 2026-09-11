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

namespace Comment\Tests\Double;

use Comment\Model\Comment;
use Comment\Repository\CommentStorageInterface;

/**
 * The comment table, in an array.
 *
 * Records what the module asked to persist so a test can assert on it, and hands back the
 * same object on a second read the way Propel's instance pool does.
 */
final class InMemoryCommentStorage implements CommentStorageInterface
{
    /** @var array<int, Comment> */
    private array $rows = [];

    /** @var list<Comment> every comment handed to save(), in order */
    public array $saved = [];

    private int $nextId = 1;

    public function store(Comment ...$comments): void
    {
        foreach ($comments as $comment) {
            $id = $comment->getId() ?? $this->nextId;
            $comment->setId($id);
            $this->rows[$id] = $comment;
            $this->nextId = max($this->nextId, $id + 1);
        }
    }

    public function findById(int $id): ?Comment
    {
        return $this->rows[$id] ?? null;
    }

    public function save(Comment $comment): void
    {
        $comment->save();
        $this->store($comment);
        $this->saved[] = $comment;
    }
}
