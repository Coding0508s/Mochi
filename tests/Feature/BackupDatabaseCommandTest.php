<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    private string $sqlitePath;

    private string $backupPath = 'backups-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePath = storage_path('framework/testing/backup-command.sqlite');
        if (! is_dir(dirname($this->sqlitePath))) {
            mkdir(dirname($this->sqlitePath), 0777, true);
        }

        if (is_file($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }

        touch($this->sqlitePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->sqlitePath);
        Config::set('database_backup.enabled', true);
        Config::set('database_backup.disk', 'local');
        Config::set('database_backup.path', $this->backupPath);
        Config::set('database_backup.keep', 2);
        Config::set('database_backup.connection', 'sqlite');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->dropIfExists('backup_probe');
        Schema::connection('sqlite')->create('backup_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Storage::disk('local')->deleteDirectory($this->backupPath);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory($this->backupPath);

        if (is_file($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }

        parent::tearDown();
    }

    public function test_skips_when_disabled_without_force(): void
    {
        Config::set('database_backup.enabled', false);

        $this->artisan('db:backup')
            ->expectsOutputToContain('DB_BACKUP_ENABLED=false')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('local')->files($this->backupPath));
    }

    public function test_refuses_non_production_without_force(): void
    {
        $this->assertFalse(app()->isProduction());

        $this->artisan('db:backup')
            ->expectsOutputToContain('운영(production) 환경에서만')
            ->assertFailed();

        $this->assertSame([], Storage::disk('local')->files($this->backupPath));
    }

    public function test_creates_sqlite_backup_with_force(): void
    {
        $this->artisan('db:backup', ['--force' => true])
            ->expectsOutputToContain('백업 완료')
            ->assertSuccessful();

        $files = Storage::disk('local')->files($this->backupPath);
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/mocchi-backup-sqlite-.+\.sqlite$/', $files[0]);
        $this->assertGreaterThan(0, Storage::disk('local')->size($files[0]));
    }

    public function test_prunes_old_backups_beyond_keep(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory($this->backupPath);

        $oldOne = $this->backupPath.'/mocchi-backup-sqlite-2020-01-01_000000.sqlite';
        $oldTwo = $this->backupPath.'/mocchi-backup-sqlite-2020-01-02_000000.sqlite';
        $oldThree = $this->backupPath.'/mocchi-backup-sqlite-2020-01-03_000000.sqlite';

        $disk->put($oldOne, 'old-1');
        $disk->put($oldTwo, 'old-2');
        $disk->put($oldThree, 'old-3');

        $root = storage_path('app/private');
        touch($root.'/'.$oldOne, time() - 300);
        touch($root.'/'.$oldTwo, time() - 200);
        touch($root.'/'.$oldThree, time() - 100);

        $this->artisan('db:backup', ['--force' => true])->assertSuccessful();

        $files = $disk->files($this->backupPath);
        $this->assertCount(2, $files);
        $this->assertFalse($disk->exists($oldOne));
        $this->assertFalse($disk->exists($oldTwo));
        $this->assertTrue($disk->exists($oldThree));
    }

    public function test_schedule_registers_weekly_backup_event(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('db:backup', $output);
    }
}
