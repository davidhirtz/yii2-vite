<?php

declare(strict_types=1);

namespace Hirtz\Vite;

use Hirtz\Skeleton\Console\Application as ConsoleApplication;
use Hirtz\Skeleton\Web\Application as WebApplication;
use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
    /**
     * @param ConsoleApplication|WebApplication<\Hirtz\Skeleton\Models\User> $app
     */
    public function bootstrap($app): void
    {
        $app->extendComponent('vite', [
            'class' => Vite::class,
        ]);
    }
}
