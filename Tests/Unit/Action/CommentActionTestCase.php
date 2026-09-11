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

namespace Comment\Tests\Unit\Action;

use Comment\Action\CommentAction;
use Comment\Tests\Double\InMemoryCommentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Template\ParserInterface;
use Thelia\Mailer\MailerFactory;

/**
 * A CommentAction wired to doubles: no kernel, no database, no mail.
 *
 * The dispatcher is a real one with no listeners, so the rating recomputation the action
 * asks for is a no-op here and is exercised on its own listener instead.
 */
abstract class CommentActionTestCase extends TestCase
{
    protected InMemoryCommentStorage $storage;

    protected EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->storage = new InMemoryCommentStorage();
        $this->dispatcher = new EventDispatcher();
    }

    protected function action(): CommentAction
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CommentAction(
            $translator,
            $this->createMock(ParserInterface::class),
            $this->createMock(MailerFactory::class),
            $this->dispatcher,
            $this->storage,
        );
    }
}
