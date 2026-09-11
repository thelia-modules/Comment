<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*************************************************************************************/

namespace Comment\Api\Resource\Addon;

use ApiPlatform\Metadata\Operation;
use Comment\Service\CommentElementPurger;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Thelia\Api\Resource\Content;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;

/**
 * Takes a content's comments away when the content is deleted through the API.
 *
 * `content` is a reference comments may be attached to, just like `product`, and the bridge's
 * PropelRemoveProcessor deletes the row itself without dispatching CONTENT_DELETE: the
 * module's event listener never runs for an API request, and a reference pair is not a
 * foreign key the database could cascade.
 *
 * Carries no property of its own on purpose. An addon is serialized only when one of its
 * properties belongs to the groups being read, so this one never shows up in a content
 * payload — it exists for doDelete() and nothing else. The rating fields stay with the
 * product: a content is not rated.
 */
class CommentContentPurge implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    /** The reference the content rows of the comment table are stored under. */
    private const REF = 'content';

    public static function getResourceParent(): string
    {
        return Content::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        // Nothing to read: there is no column to join and no field to serialize.
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // Nothing to write: a comment is never created through the content resource.
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        // ActiveRecordInterface declares no getId(); every generated model has one, a double
        // of the interface does not.
        if (!method_exists($activeRecord, 'getId')) {
            return;
        }

        $contentId = $activeRecord->getId();

        if (null === $contentId) {
            return;
        }

        CommentElementPurger::standalone()->purge(self::REF, (int) $contentId);
    }
}
