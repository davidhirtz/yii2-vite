## Simple [Vite](https://vitejs.dev/) component for [Yii 2 framework](https://www.yiiframework.com/).

Vite build option `manifest` is required to generate manifest.json file.

### Default component configuration

```php
'components' => [
    'vite' => [
        'class' => \davidhirtz\yii2\vite\components\Vite::class,
        'baseUrl' => '/dist/',
        'checkDevServer' => true,
        'devBaseUrl' => 'http://localhost:5173/',
        'devBaseUrlInternal' => null,
        'manifestPath' = '@webroot/dist/.vite/manifest.json',
        'useDevServer' = YII_ENV_DEV
    ],
],
```

### Usage

```php
Yii::$app->get('vite')->register('/resources/js/app.ts');
```