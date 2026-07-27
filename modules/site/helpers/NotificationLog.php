<?php

namespace modules\site\helpers;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Db;

/**
 * Durable record of which subscriber-notification emails have been sent
 * (or attempted) for which blog entries. Lives in the `blog_notifications`
 * table, which is created on demand.
 */
class NotificationLog
{
    public const TABLE = '{{%blog_notifications}}';

    private static bool $tableChecked = false;

    /**
     * Whether notifications have already been attempted for this entry.
     */
    public static function wasSent(int $entryId): bool
    {
        self::ensureTable();

        return (new Query())
            ->from(self::TABLE)
            ->where(['entryId' => $entryId])
            ->exists();
    }

    /**
     * Summary of past sends for an entry, or null if none.
     *
     * @return array{ok: int, failed: int, lastSent: string}|null
     */
    public static function summary(int $entryId): ?array
    {
        // Read-only: don't create the table just to look at it.
        if (!Craft::$app->getDb()->tableExists(self::TABLE)) {
            return null;
        }

        $row = (new Query())
            ->select([
                'ok' => 'SUM(success)',
                'failed' => 'SUM(1 - success)',
                'lastSent' => 'MAX(dateSent)',
            ])
            ->from(self::TABLE)
            ->where(['entryId' => $entryId])
            ->one();

        if (!$row || $row['lastSent'] === null) {
            return null;
        }

        return [
            'ok' => (int)$row['ok'],
            'failed' => (int)$row['failed'],
            'lastSent' => $row['lastSent'],
        ];
    }

    public static function record(int $entryId, string $email, bool $success): void
    {
        self::ensureTable();

        Craft::$app->getDb()->createCommand()->insert(self::TABLE, [
            'entryId' => $entryId,
            'email' => $email,
            'success' => $success,
            'dateSent' => Db::prepareDateForDb(new \DateTime()),
        ])->execute();
    }

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;

        $db = Craft::$app->getDb();
        if ($db->tableExists(self::TABLE)) {
            return;
        }

        $db->createCommand()->createTable(self::TABLE, [
            'id' => 'pk',
            'entryId' => 'integer NOT NULL',
            'email' => 'string NOT NULL',
            'success' => 'boolean NOT NULL',
            'dateSent' => 'datetime NOT NULL',
        ])->execute();

        // Backfill: posts that were already live before this table existed
        // must be marked as sent, or editing one would trigger a fresh round
        // of notification emails.
        $liveEntryIds = Entry::find()->section('blog')->ids();
        if ($liveEntryIds) {
            $now = Db::prepareDateForDb(new \DateTime());
            $db->createCommand()->batchInsert(
                self::TABLE,
                ['entryId', 'email', 'success', 'dateSent'],
                array_map(static fn($id) => [$id, '(published before notification logging)', true, $now], $liveEntryIds)
            )->execute();
        }
    }
}
