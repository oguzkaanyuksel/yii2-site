<?php

namespace portalium\site\bundles;

use yii\web\AssetBundle;

class ProfileAsset extends AssetBundle
{
    public $sourcePath= '@vendor/portalium/yii2-site/src/assets/';
    public $css = [
        'css/profile.css'
    ];
    public $js = [
        'js/access-token.js'
    ];
    public $depends = [
        'yii\web\JqueryAsset',
    ];
    public $publishOptions = [
        'forceCopy' => YII_DEBUG,
    ];
    public function init(): void
    {
        parent::init();
    }
}