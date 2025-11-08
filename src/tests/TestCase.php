<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected static $setUpHasRunOnce = false;

    /**
     * テストメソッドの前に実行される処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // テストスイートの最初の実行でのみシーダーを呼び出す
        if (! static::$setUpHasRunOnce) {
            // シーダーを一度だけ実行
            Artisan::call('db:seed');

            // フラグを立てて、二度目以降の実行を防ぐ
            static::$setUpHasRunOnce = true;
        }
    }

}
