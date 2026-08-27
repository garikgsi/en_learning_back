<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notifications\NotificationPublisher;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PublishAppReleaseNotification extends Command
{
    protected $signature = 'notifications:publish-release {version}';

    protected $description = 'Notify all users about a new Android app release';

    public function handle(NotificationPublisher $notificationPublisher): int
    {
        $version = (string) $this->argument('version');

        if (preg_match('/^\d+\.\d+\.\d+[-+._a-zA-Z0-9]*$/', $version) !== 1) {
            $this->error('Version must be a valid Android version name.');

            return SymfonyCommand::FAILURE;
        }

        $publishedCount = 0;

        User::query()
            ->orderBy('id')
            ->chunk(100, function ($users) use (
                $notificationPublisher,
                $version,
                &$publishedCount,
            ): void {
                foreach ($users as $user) {
                    $notification = $notificationPublisher
                        ->appReleaseAvailable($user, $version);

                    if ($notification->wasRecentlyCreated) {
                        $publishedCount++;
                    }
                }
            });

        $this->info("Release notifications created: {$publishedCount}.");

        return SymfonyCommand::SUCCESS;
    }
}
