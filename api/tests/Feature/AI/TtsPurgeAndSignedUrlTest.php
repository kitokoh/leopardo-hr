<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Issue #5631 (RGPD) — fichiers TTS : URLs signées expirantes (plus de lien
 * public permanent) + purge automatique (tts:purge, horaire).
 */
class TtsPurgeAndSignedUrlTest extends TestCase
{
    private const TTS_DIR = 'tts';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_audio_url_is_a_temporary_signed_route(): void
    {
        $url = URL::temporarySignedRoute('tts.audio', now()->addMinutes(5), ['filename' => 'tts_abc123.mp3']);

        $this->assertStringContainsString('tts/tts_abc123.mp3', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    public function test_valid_signed_url_streams_the_audio_file(): void
    {
        Storage::disk('local')->put('tts/tts_valid.mp3', 'FAKE-AUDIO-CONTENT');

        $url = URL::temporarySignedRoute('tts.audio', now()->addMinutes(5), ['filename' => 'tts_valid.mp3']);

        $this->get($url)
            ->assertOk()
            ->assertSee('FAKE-AUDIO-CONTENT', false);
    }

    public function test_expired_signed_url_is_rejected(): void
    {
        Storage::disk('local')->put('tts/tts_expired.mp3', 'FAKE');

        $expiredUrl = URL::temporarySignedRoute('tts.audio', now()->subMinutes(1), ['filename' => 'tts_expired.mp3']);

        $this->get($expiredUrl)->assertForbidden();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        Storage::disk('local')->put('tts/tts_tampered.mp3', 'FAKE');

        $url = URL::temporarySignedRoute('tts.audio', now()->addMinutes(5), ['filename' => 'tts_tampered.mp3']);
        $url = str_replace('tts_tampered.mp3', 'tts_other.mp3', $url);

        $this->get($url)->assertForbidden();
    }

    public function test_non_tts_filename_is_rejected(): void
    {
        Storage::disk('local')->put('tts/evil.txt', 'x');

        $url = URL::temporarySignedRoute('tts.audio', now()->addMinutes(5), ['filename' => 'evil.txt']);

        $this->get($url)->assertNotFound();
    }

    public function test_purge_command_deletes_only_files_older_than_threshold(): void
    {
        $disk = Storage::disk('local');
        $disk->put('tts/tts_old.mp3', 'OLD');
        $disk->put('tts/tts_fresh.mp3', 'FRESH');
        $disk->put('tts/tts_fresh2.mp3', 'FRESH2');

        // Vieillit tts_old.mp3 de 3 heures ; les autres restent récents.
        $oldPath = $disk->path('tts/tts_old.mp3');
        touch($oldPath, now()->subHours(3)->getTimestamp());

        $this->artisan('tts:purge', ['--older-than' => 60])
            ->expectsOutputToContain('1 fichier(s) supprimé(s)')
            ->assertSuccessful();

        $this->assertFalse($disk->exists('tts/tts_old.mp3'), 'le fichier vieux doit être purgé');
        $this->assertTrue($disk->exists('tts/tts_fresh.mp3'), 'le fichier récent doit être conservé');
        $this->assertTrue($disk->exists('tts/tts_fresh2.mp3'), 'le fichier récent doit être conservé');
    }
}
