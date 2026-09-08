<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace Comment\Hook;

use Comment\Comment;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

/**
 * Class BackHook
 * @package Comment\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class BackHook extends BaseHook
{
    /**
     * Only the tools menu entry is declared for now: it renders no template. The hooks that
     * render one (module.configuration, product.tab-content, content.tab-content, the JS
     * ones) are declared once their Smarty templates have been ported to Twig for the
     * default-twig back-office.
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                [
                    'type' => 'back',
                    'method' => 'onMainTopMenuTools',
                ],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $event->add($this->render("configuration.html"));
    }

    /**
     * Add a new entry in the admin tools menu
     *
     * should add to event a fragment with fields : id,class,url,title
     *
     * @param HookRenderBlockEvent $event
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event)
    {
        $event->add(
            [
                'id' => 'tools_menu_comment',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/module/comments'),
                'title' => $this->trans('Comments', [], Comment::MESSAGE_DOMAIN)
            ]
        );
    }

    /**
     * Add module-wide javascript.
     *
     * @param HookRenderEvent $event
     */
    public function onMainFooterJs(HookRenderEvent $event)
    {
        $event->add($this->addJS('assets/js/comment.js'));
    }

    public function onProductTabContent(HookRenderEvent $event)
    {
        $this->onTabContent($event, 'product');
    }

    public function onContentTabContent(HookRenderEvent $event)
    {
        $this->onTabContent($event, 'content');
    }

    protected function onTabContent(HookRenderEvent $event, $ref)
    {
        $event->add(
            $this->render(
                'tab-content.html',
                [
                    'ref' => $ref,
                    'id' => $event->getArgument('id')
                ]
            )
        );
    }

    public function onJsTabContent(HookRenderEvent $event)
    {
        $event->add(
            $this->addJS('assets/js/comment.js')
        );
    }
}
