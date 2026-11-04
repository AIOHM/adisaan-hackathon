<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SignalQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

final class AttachBriefAudioCommand extends Command
{
    protected $signature = 'tbwim:signal-audio {id} {file?} {--clear : Remove the attached audio instead}';

    protected $description = "Attach the spoken brief audio (AdiSaan's voice) to a signal question";

    /** Spoken briefs are written for about a minute: 90–150 words. */
    private const TARGET_WPM = 140.0;

    private const MIN_WPM = 90.0;

    private const MAX_WPM = 190.0;

    public function handle(): int
    {
        $row = SignalQuestion::query()->find($this->argument('id'));
        if (! $row) {
            $this->error('No signal question with that id.');

            return self::FAILURE;
        }

        if ((bool) $this->option('clear')) {
            $row->brief_audio_path = null;
            $row->save();
            $this->info('Audio cleared from signal question #'.$row->id.'.');

            return self::SUCCESS;
        }

        $file = (string) ($this->argument('file') ?? '');
        if ($file === '' || ! is_file($file)) {
            $this->error('Give an existing audio file (mp3, m4a, wav or ogg).');

            return self::FAILURE;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($ext, ['mp3', 'm4a', 'wav', 'ogg'], true)) {
            $this->error('Unsupported audio type: '.$ext.' (use mp3, m4a, wav or ogg).');

            return self::FAILURE;
        }

        $name = ($row->episode_ref ?? 'signal-question-'.$row->id).'.'.$ext;
        $dir = public_path('assets/adisaan/site/briefs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $dest = $dir.'/'.$name;
        if (! $this->copyAudio($file, $dest, (string) $row->brief_script)) {
            $this->error('Could not copy the audio file.');

            return self::FAILURE;
        }

        $row->brief_audio_path = '/assets/adisaan/site/briefs/'.$name;
        $row->save();

        $this->info('Audio attached: '.$row->brief_audio_path);

        return self::SUCCESS;
    }

    /**
     * Copy the recording to its public home. A take spoken far too slowly
     * or too fast sounds distorted next to the written brief (the 2 Nov
     * brief ran at ~65 words a minute against a ~140 target), so an
     * out-of-range take is tempo-corrected with pitch preserved and
     * loudness-normalised on the way in. Without ffmpeg/ffprobe — or when
     * the file cannot be probed — the recording is attached as recorded.
     */
    private function copyAudio(string $file, string $dest, string $script): bool
    {
        $duration = $this->durationSeconds($file);
        $words = str_word_count($script);
        if ($duration === null || $duration <= 0.0 || $words === 0) {
            return copy($file, $dest);
        }

        $wpm = $words / ($duration / 60.0);
        if ($wpm >= self::MIN_WPM && $wpm <= self::MAX_WPM) {
            return copy($file, $dest);
        }

        $factor = max(0.6, min(1.8, self::TARGET_WPM / $wpm));
        try {
            $result = Process::timeout(180)->run([
                'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error',
                '-i', $file,
                '-af', sprintf('atempo=%.3f,loudnorm=I=-16:TP=-1.5:LRA=11', $factor),
                '-ar', '44100', '-ac', '1',
                $dest,
            ]);
        } catch (\Throwable) {
            $result = null;
        }
        if ($result === null || ! $result->successful() || ! is_file($dest) || filesize($dest) === 0) {
            if (is_file($dest)) {
                unlink($dest);
            }
            $this->warn(sprintf('Brief audio runs at %.0f words a minute; attached as recorded (tempo correction unavailable).', $wpm));

            return copy($file, $dest);
        }

        $this->info(sprintf('Brief audio corrected from %.0f to about %.0f words a minute (pitch preserved).', $wpm, $wpm * $factor));

        return true;
    }

    private function durationSeconds(string $file): ?float
    {
        try {
            $result = Process::timeout(30)->run([
                'ffprobe', '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'csv=p=0',
                $file,
            ]);
        } catch (\Throwable) {
            return null;
        }
        $raw = trim($result->successful() ? $result->output() : '');

        return is_numeric($raw) ? (float) $raw : null;
    }
}
