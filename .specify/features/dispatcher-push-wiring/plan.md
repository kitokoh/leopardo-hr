## Plan technique
1. `NotificationDispatcher::dispatch()` : dispatcher `SendPushNotificationJob` (try/catch + Log::warning, best-effort) ; fusionner `action_url` dans les métadonnées.
2. Nouveau test `tests/Feature/Notification/NotificationDispatcherPushTest.php` (table `app_notifications` créée manuellement — dette #1813, pattern TaxSlabValidationWorkflowTest) : création in-app, `Queue::assertPushed` avec/sans action_url.
3. `php -l` + CHANGELOG + PR `fix/2252-dispatcher-push` (`Closes #2252`).
