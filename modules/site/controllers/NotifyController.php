<?php

namespace modules\site\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use modules\site\helpers\MailNotificationHelper;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class NotifyController extends Controller
{
    /**
     * Sends (or re-sends) the new-post notification for a blog entry to all
     * subscribers. Triggered by the button in the entry sidebar.
     */
    public function actionResend(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('accessCp');

        $entryId = (int)$this->request->getRequiredBodyParam('entryId');
        $entry = Entry::find()->section('blog')->id($entryId)->status(null)->one();
        if (!$entry) {
            throw new BadRequestHttpException("No blog entry with ID $entryId.");
        }

        $result = MailNotificationHelper::notifySubscribersOfEntry($entry);

        $message = "Notification sent to {$result['sent']} subscriber(s)."
            . ($result['failed'] ? " {$result['failed']} failed — see storage/logs/blog-notifications." : '');

        return $this->asJson([
            'success' => $result['failed'] === 0,
            'message' => $message,
        ]);
    }
}
