<?php

use portalium\site\bundles\SettingAsset;
use yii\helpers\Html;

/**
 * Module list on the left, the open module's fields on the right; shared by the settings and preferences pages.
 *
 * @var yii\web\View $this
 * @var array $settingsGroup settings grouped by module key
 * @var yii\widgets\ActiveForm $form
 * @var string $fieldsView view that renders one module's fields
 * @var string $id id of the tab list
 */

SettingAsset::register($this);

$tabsData = [];
foreach ($settingsGroup as $module => $items) {
    // Package migrations run for every installed package, so a module missing from the app config can still own settings.
    $moduleInstance = Yii::$app->getModule($module);
    if ($moduleInstance === null) {
        continue;
    }
    // Each module names its own icon (Module::$icon); modules outside portalium\base\Module fall back to a cog.
    $icon = property_exists($moduleInstance, 'icon') && $moduleInstance::$icon ? $moduleInstance::$icon : 'cog';
    $label = $moduleInstance->t($moduleInstance::$name);
    $label = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    $tabsData[] = [
        'label' => Html::tag('i', '', ['class' => 'fa fa-fw fa-' . $icon, 'aria-hidden' => 'true'])
            . Html::encode($label),
        'sortKey' => $label,
        'content' => Html::tag('h2', Html::encode($label), ['class' => 'setting-pane-title'])
            . $this->render($fieldsView, ['settings' => $items, 'form' => $form]),
        'options' => ['id' => 'setting-' . $module],
        'linkOptions' => [],
    ];
}
$collator = class_exists('Collator') ? new Collator(Yii::$app->language) : null;
usort($tabsData, fn ($a, $b) => $collator ? $collator->compare($a['sortKey'], $b['sortKey']) : strcasecmp($a['sortKey'], $b['sortKey']));
foreach ($tabsData as &$tab) {
    unset($tab['sortKey']);
}
unset($tab);

echo Html::beginTag('div', ['class' => 'setting-layout']);
echo \portalium\theme\widgets\Tabs::widget([
    'items' => $tabsData,
    'navType' => 'nav-pills',
    'encodeLabels' => false,
    'options' => ['class' => 'setting-nav', 'aria-orientation' => 'vertical'],
    'tabContentOptions' => ['class' => 'setting-content'],
    'id' => $id,
]);
echo Html::endTag('div');
