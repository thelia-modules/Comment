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

declare(strict_types=1);

namespace Comment;

use Comment\Model\CommentQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Translation\Translator;
use Thelia\Core\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

/**
 * Class Comment
 * @package Comment
 *
 * @author Michaël Espeche <michael.espeche@gmail.com>
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Comment extends BaseModule
{
    public const MESSAGE_DOMAIN = "comment";
    public const MESSAGE_DOMAIN_EMAIL = "comment.email.default";

    /**  Use comment */
    public const CONFIG_ACTIVATED = 1;

    /**  Use moderation */
    public const CONFIG_MODERATE = 1;

    /** Allowed ref */
    public const CONFIG_REF_ALLOWED = 'product,content';

    /** Only customers are abled to post comment */
    public const CONFIG_ONLY_CUSTOMER = 1;

    /** Allow only verified customer (for product, customers that have bought the product) */
    public const CONFIG_ONLY_VERIFIED = 1;

    /** request customer comment, x days after an order */
    public const CONFIG_REQUEST_CUSTOMMER_TTL = 15;

    /** Send an email notification to the store admins when a new comment is posted */
    public const CONFIG_NOTIFY_ADMIN_NEW_COMMENT = true;

    public const CONFIG_MAX_RATING = 5;

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // Config
        if (null === ConfigQuery::read('comment_activated')) {
            ConfigQuery::write('comment_activated', Comment::CONFIG_ACTIVATED);
        }

        if (null === ConfigQuery::read('comment_moderate')) {
            ConfigQuery::write('comment_moderate', Comment::CONFIG_MODERATE);
        }

        if (null === ConfigQuery::read('comment_ref_allowed')) {
            ConfigQuery::write('comment_ref_allowed', Comment::CONFIG_REF_ALLOWED);
        }

        if (null === ConfigQuery::read('comment_only_customer')) {
            ConfigQuery::write('comment_only_customer', Comment::CONFIG_ONLY_CUSTOMER);
        }

        if (null === ConfigQuery::read('comment_only_verified')) {
            ConfigQuery::write('comment_only_verified', Comment::CONFIG_ONLY_VERIFIED);
        }

        if (null === ConfigQuery::read('comment_request_customer_ttl')) {
            ConfigQuery::write('comment_request_customer_ttl', Comment::CONFIG_REQUEST_CUSTOMMER_TTL);
        }

        if (null === ConfigQuery::read('comment_notify_admin_new_comment')) {
            ConfigQuery::write('comment_notify_admin_new_comment', Comment::CONFIG_NOTIFY_ADMIN_NEW_COMMENT);
        }

        if (null === ConfigQuery::read('comment_max_rating')) {
            ConfigQuery::write('comment_max_rating', Comment::CONFIG_MAX_RATING);
        }

        // Schema
        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . DS . 'Config' . DS . 'thelia.sql']);
            self::setConfigValue('is_initialized', true);
        }

        // Messages
        // load the email localization files (the module was just loaded so they are not loaded yet)
        $languages = LangQuery::create()->find();
        /** @var Lang $language */
        foreach ($languages as $language) {
            Translator::getInstance()->addResource(
                "php",
                __DIR__ . "/I18n/email/default/" . $language->getLocale() . ".php",
                $language->getLocale(),
                self::MESSAGE_DOMAIN_EMAIL
            );
        }

        // Request comment from customer
        if (null === MessageQuery::create()->findOneByName('comment_request_customer')) {
            $message = new Message();
            $message
                ->setName('comment_request_customer')
                // The stored names carry no .twig: TwigParser resolves ".html" to ".html.twig"
                // and ".txt" to ".txt.twig", so these keep working after the Twig port.
                ->setHtmlTemplateFileName('request-customer-comment.html')
                // Wrapping in the shop's own email chrome, through Thelia's layout mechanism:
                // the layout renders {{ message_body }}, so the template stays layout-agnostic.
                ->setHtmlLayoutFileName('email-layout.html.twig')
                ->setTextTemplateFileName('request-customer-comment.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setTitle(
                    Translator::getInstance()->trans('Request customer comment', [], self::MESSAGE_DOMAIN)
                );
                $message->setSubject(
                    Translator::getInstance()->trans(
                        'Share your opinion on your recent order',
                        [],
                        self::MESSAGE_DOMAIN_EMAIL,
                        $locale
                    )
                );
            }

            $message->save();
        }

        // Notify admin of new comment
        if (null === MessageQuery::create()->findOneByName('new_comment_notification_admin')) {
            $message = new Message();
            $message
                ->setName('new_comment_notification_admin')
                ->setHtmlTemplateFileName('new-comment-notification-admin.html')
                ->setHtmlLayoutFileName('email-layout.html.twig')
                ->setTextTemplateFileName('new-comment-notification-admin.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setTitle(
                    Translator::getInstance()->trans(
                        'Notify store admin of new comment',
                        [],
                        self::MESSAGE_DOMAIN_EMAIL,
                        $locale
                    )
                );

                $subject = Translator::getInstance()->trans(
                    'New comment on %ref_type_title "%ref_title"',
                    [],
                    self::MESSAGE_DOMAIN_EMAIL,
                    $locale
                );
                // The subject is compiled as an inline template by the parser: Twig placeholders
                // now, the Smarty ones went out with the rest of the port.
                $subject = str_replace('%ref_type_title', '{{ ref_type_title|lower }}', $subject);
                $subject = str_replace('%ref_title', '{{ ref_title }}', $subject);
                $message->setSubject($subject);
            }

            $message->save();
        }
    }

    public static function getConfig(): array
    {
        $config = [
            'activated' => (
                (int)ConfigQuery::read('comment_activated', self::CONFIG_ACTIVATED) === 1
            ),
            'moderate' => (
                (int)ConfigQuery::read('comment_moderate', self::CONFIG_MODERATE) === 1
            ),
            'ref_allowed' => explode(
                ',',
                ConfigQuery::read('comment_ref_allowed', self::CONFIG_REF_ALLOWED)
            ),
            'only_customer' => (
                (int)ConfigQuery::read('comment_only_customer', self::CONFIG_ONLY_CUSTOMER) === 1
            ),
            'only_verified' => (
                (int)ConfigQuery::read('comment_only_verified', self::CONFIG_ONLY_VERIFIED) === 1
            ),
            'request_customer_ttl' => (
                (int)ConfigQuery::read('comment_request_customer_ttl', self::CONFIG_REQUEST_CUSTOMMER_TTL)
            ),
            'notify_admin_new_comment' => (
                (int)ConfigQuery::read('comment_notify_admin_new_comment', self::CONFIG_NOTIFY_ADMIN_NEW_COMMENT)
                    === 1
            ),
            'max_rating' => (
                (int)ConfigQuery::read('comment_max_rating', self::CONFIG_MAX_RATING)
                    === 1
            ),
        ];

        return $config;
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
