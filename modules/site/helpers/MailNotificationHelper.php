<?php

namespace modules\site\helpers;

use Craft;
use craft\elements\Entry;
use craft\mail\Message;
use craft\web\View;

class MailNotificationHelper {

    /** Log category picked up by the dedicated blog-notifications log target. */
    public const LOG_CATEGORY = 'blog-notifications';

    /**
     * Sends the "new post" notification to every enabled subscriber and
     * records each attempt in the notification log.
     *
     * @return array{sent: int, failed: int}
     */
    public static function notifySubscribersOfEntry(Entry $entry): array
    {
        $subscribers = Entry::find()
            ->section('subscribers')
            ->all();

        $sent = 0;
        $failed = 0;

        foreach ($subscribers as $subscriber) {
            /** @var Entry $subscriber */
            $email = $subscriber->getFieldValue('email');
            if (!$email) {
                continue;
            }

            $success = self::sendEmailNotification(
                $email,
                'New Sumner Mission Story Published: ' . $entry->title,
                'site/_email/post-notification',
                [
                    'entry' => $entry,
                    'subscriberId' => $subscriber->id,
                ]
            );

            NotificationLog::record($entry->id, $email, $success);
            $success ? $sent++ : $failed++;
        }

        Craft::info(
            "Post notification for entry {$entry->id} (\"{$entry->title}\"): {$sent} sent, {$failed} failed.",
            self::LOG_CATEGORY
        );

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Renders a template and sends it as a single email.
     */
    public static function sendEmailNotification(string|array $to, string $subject, string $template, array $templateVariables = []): bool
    {
        $toLabel = implode(', ', array_map(
            static fn($k, $v) => is_string($k) ? $k : $v,
            array_keys((array)$to),
            (array)$to
        ));

        try {
            $message = new Message();
            $message->setTo($to);
            $message->setFrom([
                'hello@mail.sumnermission.org' => 'Another Side of Heaven',
            ]);
            $message->setSubject($subject);
            $emailHtml = Craft::$app->getView()->renderTemplate($template,
                $templateVariables,
                View::TEMPLATE_MODE_CP
            );
            $message->setHtmlBody($emailHtml);
            $success = Craft::$app->getMailer()->send($message);
        } catch (\Throwable $e) {
            Craft::error("Error sending \"$subject\" to $toLabel: " . $e->getMessage(), self::LOG_CATEGORY);
            return false;
        }

        if ($success) {
            Craft::info("Sent \"$subject\" to $toLabel", self::LOG_CATEGORY);
        } else {
            Craft::warning("Mailer failed to send \"$subject\" to $toLabel — check the email transport settings (Brevo API key).", self::LOG_CATEGORY);
        }

        return $success;
    }
}
