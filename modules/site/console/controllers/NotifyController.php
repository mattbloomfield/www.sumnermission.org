<?php

namespace modules\site\console\controllers;

use craft\console\Controller;
use craft\db\Query;
use craft\elements\Entry;
use modules\site\helpers\MailNotificationHelper;
use modules\site\helpers\NotificationLog;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manual controls for the new-post subscriber notifications.
 *
 *   php craft site/notify/status                 — show the notification log
 *   php craft site/notify/test --to=you@x.com    — send one test notification (latest live post)
 *   php craft site/notify/send --entry-id=123    — notify all subscribers about an entry
 */
class NotifyController extends Controller
{
    /** @var int|null Blog entry ID to send notifications for. */
    public ?int $entryId = null;

    /** @var string|null Single email address to send a test notification to (nothing is recorded). */
    public ?string $to = null;

    /** @var bool Send even if the notification log says this entry was already handled. */
    public bool $force = false;

    public function options($actionID): array
    {
        return match ($actionID) {
            'send' => ['entryId', 'force'],
            'test' => ['entryId', 'to'],
            default => [],
        };
    }

    /**
     * Sends the new-post notification to all subscribers for the given --entry-id.
     */
    public function actionSend(): int
    {
        if (!$this->entryId) {
            $this->stderr("--entry-id is required.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $entry = Entry::find()->section('blog')->id($this->entryId)->status(null)->one();
        if (!$entry) {
            $this->stderr("No blog entry with ID {$this->entryId}.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        if (NotificationLog::wasSent($entry->id) && !$this->force) {
            $this->stderr("Notifications were already attempted for entry {$entry->id} (\"{$entry->title}\"). Re-run with --force to send again.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Notifying subscribers about \"{$entry->title}\"...\n");
        $result = MailNotificationHelper::notifySubscribersOfEntry($entry);
        $color = $result['failed'] ? Console::FG_YELLOW : Console::FG_GREEN;
        $this->stdout("{$result['sent']} sent, {$result['failed']} failed.\n", $color);

        return $result['failed'] ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Sends a single test notification to --to, using --entry-id or the latest live post.
     */
    public function actionTest(): int
    {
        if (!$this->to) {
            $this->stderr("--to is required.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $query = Entry::find()->section('blog');
        if ($this->entryId) {
            $query->id($this->entryId)->status(null);
        }
        $entry = $query->one();
        if (!$entry) {
            $this->stderr("No blog entry found.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $this->stdout("Sending test notification for \"{$entry->title}\" to {$this->to}...\n");
        $success = MailNotificationHelper::sendEmailNotification(
            $this->to,
            'New Sumner Mission Story Published: ' . $entry->title,
            'site/_email/post-notification',
            [
                'entry' => $entry,
                'subscriberId' => 0,
            ]
        );

        $this->stdout($success ? "Sent.\n" : "Failed — see storage/logs/blog-notifications-*.log\n", $success ? Console::FG_GREEN : Console::FG_RED);
        return $success ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Prints the notification log (most recent first).
     */
    public function actionStatus(): int
    {
        if (!\Craft::$app->getDb()->tableExists(NotificationLog::TABLE)) {
            $this->stdout("No notifications have been sent yet (log table doesn't exist).\n");
            return ExitCode::OK;
        }

        $rows = (new Query())
            ->from(NotificationLog::TABLE)
            ->orderBy(['dateSent' => SORT_DESC])
            ->limit(100)
            ->all();

        if (!$rows) {
            $this->stdout("No notifications have been sent yet.\n");
            return ExitCode::OK;
        }

        foreach ($rows as $row) {
            $status = $row['success'] ? 'OK    ' : 'FAILED';
            $this->stdout("{$row['dateSent']}  {$status}  entry {$row['entryId']}  {$row['email']}\n", $row['success'] ? Console::FG_GREEN : Console::FG_RED);
        }

        return ExitCode::OK;
    }
}
