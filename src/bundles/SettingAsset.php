<?php

namespace portalium\site\bundles;

use yii\web\AssetBundle;

class SettingAsset extends AssetBundle
{
    public $sourcePath = '@vendor/portalium/yii2-site/src/assets/setting';

    public $css = [
        'css/setting.css',
    ];

    public $js = [
        'js/setting.js',
    ];

    public $depends = [
        'portalium\theme\bundles\AppAsset',
    ];

    public $publishOptions = [
        'forceCopy' => YII_DEBUG,
    ];
}
