<?php

namespace Tests\Feature;

use App\Domains\Backup\Backup;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Backups. SQLite can't VACUUM inside a transaction, so these use DatabaseMigrations
 * instead of RefreshDatabase (which wraps each test in one).
 */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'studio-test-'.uniqid();
        mkdir($this->storage.'/app/private/media/thumbnail', 0777, true);
        $this->app->useStoragePath($this->storage);
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function test_backup_now_list_download_and_prune(): void
    {
        Script::create(['title' => 'Keep me safe', 'body' => 'Precious words.']);
        $res = $this->postJson('/api/backups')->assertOk()->json();
        $name = $res['backups'][0]['name'];
        $this->assertMatchesRegularExpression(Backup::NAME, $name);

        // The copy is a real database with her work in it.
        $copy = new \PDO('sqlite:'.Backup::path($name));
        $this->assertSame('Keep me safe', $copy->query('select title from scripts')->fetchColumn());
        $copy = null;

        $this->get("/api/backups/{$name}")->assertOk()->assertDownload($name);
        // A path trick never reaches a file (it falls through to the app page, not a download).
        $this->get('/api/backups/..%2F.env')->assertHeaderMissing('content-disposition');
        $this->get('/api/backups/nope.sqlite')->assertNotFound();
        if (class_exists(\ZipArchive::class)) {
            file_put_contents($this->storage.'/app/private/media/thumbnail/a.png', 'png');
            $this->get("/api/backups/{$name}?media=1")->assertOk()->assertDownload(str_replace('.sqlite', '.zip', $name));
        }

        // Only the newest 14 are kept.
        for ($d = 1; $d <= 20; $d++) {
            touch(Backup::dir().'/studio-2026-01-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT).'-120000.sqlite');
        }
        Backup::run();
        $list = Backup::list();
        $this->assertCount(Backup::KEEP, $list);
        $this->assertFalse(collect($list)->contains('name', 'studio-2026-01-01-120000.sqlite'), 'oldest pruned');

        $this->artisan('studio:backup')->assertSuccessful();
        $this->assertNotNull(Setting::get('last_run_backup'));
        $this->getJson('/api/backups')->assertOk()->assertJsonPath('keep', 14);
    }

    public function test_backups_need_login(): void
    {
        auth()->logout();
        $this->getJson('/api/backups')->assertStatus(401);
        $this->postJson('/api/backups')->assertStatus(401);
    }
}
