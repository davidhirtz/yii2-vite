<?php

declare(strict_types=1);

namespace Hirtz\Vite;

use yii\base\InvalidConfigException;

class InvalidManifestException extends InvalidConfigException
{
    #[\Override]
    public function getName(): string
    {
        return 'Invalid Vite Manifest';
    }
}
