<?php

declare(strict_types=1);

$basePath = (getenv('BASE_PATH') ?: getcwd());

// No `bootstrap` key: composer's `extra.bootstrap` reaches every bundle through `vendor/yiisoft/extensions.php`,
// so naming one here would run it a second time and register its event handlers twice.
return require("$basePath/vendor/davidhirtz/yii2-skeleton/config/test.php");
