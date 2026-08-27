<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\AppReleaseAvailable;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PublishAppReleaseNotification extends Command
{
    protected $signature = 'notifications:publish-release {version}';

    protected $description = 'Notify all users about a new Android app release';

    public function handle(): int
    {
        $version = (string) $this->argument('version');

        if (preg_match('/^\d+\.\d+\.\d+[-+._a-zA-Z0-9]*$/', $version) !== 1) {
            $this->error('Version must be a valid Android version name.');

            return SymfonyCommand::FAILURE;
        }

        $publishedCount = 0;

        User::query()
            ->nonTest()
            ->orderBy('id')
            ->chunk(100, function ($users) use ($version, &$publishedCount): void {
                foreach ($users as $user) {
                    $notification = new AppReleaseAvailable($version);
                    $alreadyPublished = UserNotification::query()
                        ->where(
                            'deduplication_key',
                            $notification->deduplicationKey($user),
                        )->exists();

                    if ($alreadyPublished) {
                        continue;
                    }

                    $user->notify($notification);
                    $publishedCount++;
                }
            });

        $this->info("Release notifications created: {$publishedCount}.");

        return SymfonyCommand::SUCCESS;
    }
}
