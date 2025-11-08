<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;

trait RefreshDatabaseWithSeeding
{
    /**
     * 実行するシーダークラスを指定
     */
    protected $seeder = null;

    /**
     * データベースをリフレッシュしてシーダーを実行
     */
    public function refreshDatabaseWithSeeding()
    {
        $this->artisan('migrate:fresh');

        $seederClass = $this->seeder ?? 'Database\\Seeders\\DatabaseSeeder';
        $this->artisan('db:seed', ['--class' => $seederClass]);

        $this->app[Kernel::class]->setArtisan(null);
    }
}
