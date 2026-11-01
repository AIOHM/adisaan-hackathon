<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SignalQuestion;
use Illuminate\Console\Command;

final class AttachBriefAudioCommand extends Command
{
    protected $signature = 'tbwim:signal-audio {id} {file?} {--clear : Remove the attached audio instead}';

    protected $description = "Attach the spoken brief audio (AdiSaan's voice) to a signal question";

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
        copy($file, $dir.'/'.$name);

        $row->brief_audio_path = '/assets/adisaan/site/briefs/'.$name;
        $row->save();

        $this->info('Audio attached: '.$row->brief_audio_path);

        return self::SUCCESS;
    }
}
