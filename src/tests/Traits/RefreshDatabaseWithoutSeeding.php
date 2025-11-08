<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;

trait RefreshDatabaseWithoutSeeding
{
    /**
     * Define hooks to migrate the database once per test suite.
     * シーダーを実行せずにデータベースをリフレッシュする
     *
     * @return void
     */
    public function refreshDatabase()
    {
        // migrate:fresh を実行し、データベースをリセット
        $this->artisan('migrate:fresh');


        // アプリケーションを再起動して、新しいスキーマをロード
        $this->app[Kernel::class]->setArtisan(null);
    }
}
