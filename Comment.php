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

use Comment\Install\CommentSchemaUpgrade;
use Comment\Model\CommentQuery;
use Comment\Repository\CommentRepository;
use Comment\Repository\CommentStorageInterface;
use Comment\Repository\RatingMetaRepository;
use Comment\Repository\RatingMetaStorageInterface;
use Comment\Service\Front\CommentDefinitionResolver;
use Comment\Service\Front\CommentDefinitionResolverInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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

        // A shop installed before the current schema needs what thelia.sql only gives to a
        // first activation. Does nothing when the table is already up to date.
        (new CommentSchemaUpgrade())->apply($con);

        $languages = LangQuery::create()->find();
        $this->loadEmailTranslations($languages);

        // Request comment from customer
        if (null === MessageQuery::create()->findOneByName('comment_request_customer')) {
            $message = new Message();
            $message
                ->setName('comment_request_customer')
                // The stored names carry no .twig: TwigParser resolves ".html" to ".html.twig"
                // and ".txt" to ".txt.twig", so these keep working after the Twig port.
                ->setHtmlTemplateFileName('request-customer-comment.html')
                // No layout file name here: the template extends email-layout.html.twig itself,
                // the way the native messages do. Naming the layout as well would render it a
                // second time around the already-wrapped body.
                ->setHtmlLayoutFileName('')
                ->setTextTemplateFileName('request-customer-comment.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);
                $message->setTitle(
                    Translator::getInstance()->trans('Request customer comment', [], self::MESSAGE_DOMAIN, $locale)
                );
                $message->setSubject($this->requestCustomerSubject($locale));
            }

            $message->save();
        }

        // Notify admin of new comment
        if (null === MessageQuery::create()->findOneByName('new_comment_notification_admin')) {
            $message = new Message();
            $message
                ->setName('new_comment_notification_admin')
                ->setHtmlTemplateFileName('new-comment-notification-admin.html')
                ->setHtmlLayoutFileName('')
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
                $message->setSubject($this->newCommentAdminSubject($locale));
            }

            $message->save();
        }
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        (new CommentSchemaUpgrade())->apply($con);

        $languages = LangQuery::create()->find();
        $this->loadEmailTranslations($languages);

        $this->repairMessageSubjects($languages);
    }

    /**
     * Load the email localization files.
     *
     * postActivation() and update() both run before the module's own catalogues are registered,
     * so the strings written to the message rows would otherwise come out as their English keys.
     *
     * @param iterable<Lang> $languages
     */
    private function loadEmailTranslations(iterable $languages): void
    {
        /** @var Lang $language */
        foreach ($languages as $language) {
            Translator::getInstance()->addResource(
                'php',
                __DIR__.'/I18n/email/default/'.$language->getLocale().'.php',
                $language->getLocale(),
                self::MESSAGE_DOMAIN_EMAIL
            );
        }
    }

    /**
     * Repair the two message subjects on shops installed before the Twig port.
     *
     * The customer request went out with no subject at all, and the admin notification still
     * carries the Smarty placeholders `{$ref_type_title}` / `{$ref_title}`, which
     * Message::buildMessage() now compiles as an inline Twig template and therefore ships
     * verbatim. Only those two cases are rewritten: a subject a merchant has edited in the
     * back office is left untouched.
     *
     * @param iterable<Lang> $languages
     */
    private function repairMessageSubjects(iterable $languages): void
    {
        $subjects = [
            'comment_request_customer' => $this->requestCustomerSubject(...),
            'new_comment_notification_admin' => $this->newCommentAdminSubject(...),
        ];

        foreach ($subjects as $name => $subjectForLocale) {
            $message = MessageQuery::create()->findOneByName($name);

            if (null === $message) {
                continue;
            }

            $changed = false;

            /** @var Lang $language */
            foreach ($languages as $language) {
                $locale = $language->getLocale();
                $message->setLocale($locale);

                $current = (string) $message->getSubject();

                if ('' !== $current && !str_contains($current, '{$')) {
                    continue;
                }

                $message->setSubject($subjectForLocale($locale));
                $changed = true;
            }

            // The template wraps itself in the layout now, so a layout named on the row would
            // render the chrome twice.
            if ('email-layout.html.twig' === $message->getHtmlLayoutFileName()) {
                $message->setHtmlLayoutFileName('');
                $changed = true;
            }

            if ($changed) {
                $message->save();
            }
        }
    }

    private function requestCustomerSubject(string $locale): string
    {
        return Translator::getInstance()->trans(
            'Share your opinion on your recent order',
            [],
            self::MESSAGE_DOMAIN_EMAIL,
            $locale
        );
    }

    private function newCommentAdminSubject(string $locale): string
    {
        $subject = Translator::getInstance()->trans(
            'New comment on %ref_type_title "%ref_title"',
            [],
            self::MESSAGE_DOMAIN_EMAIL,
            $locale
        );

        // The subject is compiled as an inline template by the parser: Twig placeholders now,
        // the Smarty ones went out with the rest of the port.
        return str_replace(
            ['%ref_type_title', '%ref_title'],
            ['{{ ref_type_title|lower }}', '{{ ref_title }}'],
            $subject
        );
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
            // The maximum rating is a scale (5 by default), not a flag: comparing it to 1
            // returned false for every shop whose scale was not exactly 1.
            'max_rating' => (
                (int) ConfigQuery::read('comment_max_rating', self::CONFIG_MAX_RATING)
            ),
        ];

        return $config;
    }

    /**
     * The budgets Comment\Service\Front\CommentPostLimiter spends on every posted comment.
     *
     * Declared here rather than in the shop's framework configuration so that activating the
     * module is enough: a shop that lets anyone post gets the limit with it.
     */
    public static function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        $containerConfigurator->extension('framework', [
            'rate_limiter' => [
                'comment_post_per_client' => [
                    'policy' => 'sliding_window',
                    'limit' => 10,
                    'interval' => '1 hour',
                ],
                'comment_post_per_element' => [
                    'policy' => 'sliding_window',
                    'limit' => 3,
                    'interval' => '1 hour',
                ],
                // Reporting is a click behind no form and no account: one visitor may flag a
                // handful of comments, not walk down a page raising every counter.
                'comment_abuse_per_client' => [
                    'policy' => 'sliding_window',
                    'limit' => 10,
                    'interval' => '1 hour',
                ],
            ],
        ], prepend: true);
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            // Tests/ is part of the tree load() walks: a test double implementing an
            // autoconfigured interface would otherwise be registered as a real service.
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Tests/*'])
            ->autowire(true)
            ->autoconfigure(true);

        // load() registers services under their class name; autowiring an interface needs an
        // alias of its own.
        $servicesConfigurator->alias(CommentStorageInterface::class, CommentRepository::class);
        $servicesConfigurator->alias(RatingMetaStorageInterface::class, RatingMetaRepository::class);
        $servicesConfigurator->alias(CommentDefinitionResolverInterface::class, CommentDefinitionResolver::class);
    }
}
