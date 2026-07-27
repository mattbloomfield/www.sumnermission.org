<?php

namespace modules\site;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\ElementHelper;
use craft\log\MonologTarget;
use craft\web\View;
use modules\site\helpers\MailNotificationHelper;
use modules\site\helpers\NotificationLog;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\base\Module as BaseModule;

/**
 * Site module
 *
 * @method static Site getInstance()
 */
class Site extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/site', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\site\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\site\\controllers';
        }

        parent::init();

        $this->registerNotificationLogTarget();
        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    /**
     * Writes everything logged under the `blog-notifications` category to
     * storage/logs/blog-notifications-*.log, including info-level messages,
     * which Craft otherwise drops in production.
     */
    private function registerNotificationLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[MailNotificationHelper::LOG_CATEGORY] = new MonologTarget([
            'name' => MailNotificationHelper::LOG_CATEGORY,
            'categories' => [MailNotificationHelper::LOG_CATEGORY],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% [%level_name%] %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)

        // Base template directory
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );

        // Email subscribers the first time a blog entry is saved in the "live"
        // state — whether that's on first publish or when a previously
        // disabled/draft entry is enabled later. The NotificationLog table
        // guarantees an entry only ever triggers one round of emails.
        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            static function(ModelEvent $e) {
                /* @var Entry $entry */
                $entry = $e->sender;

                if (
                    ElementHelper::isDraftOrRevision($entry)
                    || $entry->propagating
                    || $entry->resaving
                    || $entry->section?->handle !== 'blog'
                    || $entry->getStatus() !== Entry::STATUS_LIVE
                ) {
                    return;
                }

                try {
                    if (NotificationLog::wasSent($entry->id)) {
                        return;
                    }

                    Craft::info(
                        "Blog entry {$entry->id} (\"{$entry->title}\") is live for the first time — notifying subscribers.",
                        MailNotificationHelper::LOG_CATEGORY
                    );

                    MailNotificationHelper::notifySubscribersOfEntry($entry);
                } catch (\Throwable $ex) {
                    Craft::error('Error sending post notifications: ' . $ex->getMessage(), MailNotificationHelper::LOG_CATEGORY);
                }
            }
        );

        // Add a "send/re-send notification" panel to the blog entry sidebar in the CP
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            static function(DefineHtmlEvent $e) {
                /* @var Entry $entry */
                $entry = $e->sender;
                $canonicalId = $entry->getCanonicalId();

                if (
                    $entry->getIsRevision()
                    || $entry->section?->handle !== 'blog'
                    || !$canonicalId
                ) {
                    return;
                }

                $e->html .= Craft::$app->getView()->renderTemplate(
                    'site/_cp/notify-sidebar',
                    [
                        'entryId' => $canonicalId,
                        'summary' => NotificationLog::summary($canonicalId),
                    ],
                    View::TEMPLATE_MODE_CP
                );
            }
        );

    }


}
