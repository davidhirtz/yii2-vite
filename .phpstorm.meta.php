<?php

namespace PHPSTORM_META {

    override(
        \yii\base\Module::get(0),
        map([
            'vite' => '\Hirtz\Vite\Vite',
        ])
    );
}
