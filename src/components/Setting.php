<?php

namespace portalium\site\components;

use portalium\site\models\Preference;
use yii\base\Component;
use portalium\site\Module;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\base\InvalidConfigException;
use portalium\site\models\Setting as Settings;

class Setting extends Component
{
    public function getConfig($name)
    {
        return self::decode(self::findSetting($name)->config);
    }

    public function setConfig($name, $value)
    {
        $setting = self::findSetting($name);
        $setting->config = $value;
        return $setting->save();
    }

    public function getAll()
    {
        return ArrayHelper::map(Settings::find()->asArray()->all(),'name','value');
    }

    public function getValue($name)
    {
        $settingModel = self::findSetting($name);
        $preferenceModel = self::findPreference($settingModel);

        return self::decode($preferenceModel ? $preferenceModel->value : $settingModel->value);
    }

    public static function getSetting($name)
    {
        $settingModel = self::findSetting($name);
        $preferenceModel = self::findPreference($settingModel);

        return $preferenceModel ? $preferenceModel : $settingModel;
    }

    private function decode($value)
    {
        return ($this->isJson($value,true)) ? json_decode($value, true): $value;
    }

    private static function findSetting($name)
    {
        if (($setting = Settings::findOne(['name' => $name])) !== null) {
            return $setting;
        }

        throw new NotFoundHttpException(Module::t('The requested '.$name.' setting does not exist.'));
    }

    /**
     * The user's preference for a setting: the active workspace's row first, then the row without a
     * workspace (global). Only settings marked as preferences can be overridden by a user.
     */
    private static function findPreference($settingModel)
    {
        if (!\Yii::$app->has('user') || \Yii::$app->user->id === null || (int) $settingModel->is_preference !== 1) {
            return false;
        }

        $idWorkspace = \Yii::$app->has('workspace') ? \Yii::$app->workspace->id : null;
        $preference = Preference::find()
            ->where(['id_setting' => $settingModel->id, 'id_user' => \Yii::$app->user->id])
            ->andWhere(['or', ['id_workspace' => $idWorkspace], ['id_workspace' => null]])
            ->orderBy(['id_workspace' => SORT_DESC])
            ->one();

        return $preference ?: false;
    }

    function isJson($value) {
        $value = strval($value);
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
