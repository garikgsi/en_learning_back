<?php

namespace App\Console\Commands;

use App\Services\Dictionary\Data\SpeechRequest;
use App\Services\Dictionary\Drivers\Speech\VoiceRssSpeechDriver;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CacheDachshundLetterAudio extends Command
{
    protected $signature = 'dachshund:cache-letter-audio
        {--locale=en-GB : Voice RSS language locale}
        {--voice=Lily : Voice RSS voice name}';

    protected $description = 'Generate and cache one British English recording for each alphabet letter';

    public function handle(VoiceRssSpeechDriver $speechDriver): int
    {
        $locale = (string) $this->option('locale');
        $voice = (string) $this->option('voice');
        $targetDirectory = public_path('audio/dachshund/alphabet/en-gb');

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException("Unable to create directory: $targetDirectory");
        }

        $files = [];

        foreach (range('A', 'Z') as $letter) {
            $result = $speechDriver->audio(new SpeechRequest(
                text: $letter,
                locale: $locale,
                voice: $voice,
            ));

            if ($result === null) {
                throw new RuntimeException("Voice RSS did not return audio for letter $letter.");
            }

            $filename = strtolower($letter).'.mp3';
            $path = $targetDirectory.DIRECTORY_SEPARATOR.$filename;
            file_put_contents($path, $result->contents);

            $files[] = [
                'letter' => $letter,
                'cachedFile' => $filename,
                'sha256' => hash_file('sha256', $path),
                'bytes' => filesize($path),
            ];

            $this->output->write('.');
        }

        $manifest = [
            'source' => 'Voice RSS text-to-speech',
            'sourcePage' => 'https://www.voicerss.org/api/',
            'locale' => $locale,
            'voice' => $voice,
            'format' => (string) config('services.voice_rss.audio_format'),
            'selection' => 'Each uppercase letter name is synthesized independently.',
            'usage' => 'Generated with the configured Voice RSS account; distribution is subject to its terms.',
            'files' => $files,
        ];

        file_put_contents(
            $targetDirectory.DIRECTORY_SEPARATOR.'sources.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        $this->newLine(2);
        $this->info('Cached 26 British English letter recordings with voice '.$voice.'.');

        return SymfonyCommand::SUCCESS;
    }
}
