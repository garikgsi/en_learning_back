# Push notifications

The notification record stored in `user_notifications` is the source of truth.
Firebase Cloud Messaging (FCM) is only a delivery signal that prompts the app
to synchronize the server journal into IndexedDB.

Daily exercises are created Monday through Thursday at 12:00 Moscow time;
weekly exercises are created each Friday at 12:00 Moscow time. Their first
notification is published immediately after creation. At 18:00 Moscow time,
the scheduler publishes one idempotent reminder for each daily or weekly
exercise due that day that has not been completed. User-created exercises do
not produce these notifications.

Push delivery is limited to 12:00-20:00 Moscow time. Notifications published
outside that window are stored immediately and their push jobs are delayed
until the next opening of the window.

After publishing a new Android version, create one notification per user with:

```bash
php artisan notifications:publish-release 0.1.0-rc.15
```

The command is idempotent for a user and version. Opening this notification
takes the Android app to its update screen.

## Firebase project

1. Create or select a project in the Firebase Console.
2. Add an Android app with package name `com.enlearning.app`.
3. Download `google-services.json` and place it in the client repository at
   `android/app/google-services.json`.
4. In Google Cloud Console, enable the Firebase Cloud Messaging API for the
   same project.
5. Create a dedicated service account named, for example,
   `en-learning-fcm-sender` and grant only the
   `Firebase Cloud Messaging API Admin` role
   (`roles/firebasecloudmessaging.admin`).
6. Create a JSON key for that service account and keep it outside both Git
   repositories.

The Android `google-services.json` contains app configuration, not the backend
private key. Never copy the service-account JSON into the Android project.

## Backend credentials

Store the service-account key on the production host, for example:

```text
/opt/en-learning/secrets/firebase-service-account.json
```

Mount it read-only into the `queue` container:

```yaml
services:
  queue:
    volumes:
      - /opt/en-learning/secrets/firebase-service-account.json:/run/secrets/firebase-service-account.json:ro
```

Set these values in the production backend `.env`:

```dotenv
PUSH_NOTIFICATIONS_ENABLED=true
FIREBASE_PROJECT_ID=your-firebase-project-id
GOOGLE_APPLICATION_CREDENTIALS=/run/secrets/firebase-service-account.json
FIREBASE_API_URL=https://fcm.googleapis.com/v1
FIREBASE_ANDROID_CHANNEL_ID=exercises
```

Keep `PUSH_NOTIFICATIONS_ENABLED=false` until the Android configuration and
the queue-container secret mount are both present. The notification journal
and synchronization API continue to work while push delivery is disabled.

## Activation

After adding the Android config file, run `npm run android:sync` in the client
repository. On the backend, run the new database migrations and restart the
queue worker so that it loads the Firebase configuration and Google Auth
dependency.
