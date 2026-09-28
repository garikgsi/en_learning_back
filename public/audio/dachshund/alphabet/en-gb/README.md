# British English letter recordings

The 26 MP3 files are generated separately with the British English female
Voice RSS voice `Lily`. Independent generation avoids timing and clipping
errors caused by cutting a long alphabet recording.

Run the following command in the application container to refresh the pack:

```shell
php artisan dachshund:cache-letter-audio --locale=en-GB --voice=Lily
```

`sources.json` records the provider, generation settings, and output hashes.
Use of the generated audio is subject to the terms of the configured Voice RSS
account.
