<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use portalium\theme\widgets\ActiveForm;
use portalium\site\Module;
use portalium\site\models\Setting;
use portalium\site\models\Form;
use portalium\site\helpers\ActiveForm as SettingForm;
use portalium\theme\widgets\Panel;


/** @var yii\web\View $this */
/** @var portalium\site\models\PreferenceSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title =Module::t('Preference');
$this->params['breadcrumbs'][] = $this->title;
?>
<?php $form = ActiveForm::begin(['action' => Url::to(['preference/update']), 'id' => 'preference-update', 'method' => 'post', 'class' => 'form-horizontal']); ?>
<?php Panel::begin([
    'title' => Module::t('Preference'),
    'actions' => [
        'header' => [
        ],
        'footer' => [
            Html::submitButton(Module::t('Save'), ['class' => 'btn btn-success', 'id' => 'pereference-update-submit']),
        ]
    ]
]) ?>

<?= $this->render('@portalium/site/views/web/setting/_module-tabs', [
    'settingsGroup' => $settingsGroup,
    'form' => $form,
    'fieldsView' => '@portalium/site/views/web/preference/_preference',
    'id' => 'preference-tabs',
]) ?>
<?php Panel::end() ?>
<?php ActiveForm::end(); ?>
