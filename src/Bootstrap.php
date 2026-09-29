<?php

declare(strict_types=1);

namespace Hirtz\Vite;

use Hirtz\Skeleton\Base\ConfigBootstrapInterface;
use Override;

class Bootstrap implements ConfigBootstrapInterface
{
    #[Override]
    public static function getDefaultConfig(): array
    {
        return [
            'components' => [
                'vite' => [
                    'class' => Vite::class,
                ],
            ],
        ];
    }

    #[Override]
    public function bootstrap($app): void
    {
    }
}
