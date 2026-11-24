<?php

namespace portalium\site\controllers\api;

use Yii;
use portalium\rest\Controller;
use portalium\site\components\Setting as SettingComponent;
use portalium\site\models\Form;
use portalium\site\models\Preference;
use portalium\site\models\Setting;
use portalium\site\models\SettingValue;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * The signed-in user's own preferences. Only settings marked as preferences (is_preference = 1)
 * can be read or overridden here. A preference without a workspace applies to every workspace;
 * a workspace preference wins over it.
 */
class PreferenceController extends Controller
{
    protected function verbs()
    {
        return [
            'index' => ['GET', 'HEAD'],
            'view' => ['GET', 'HEAD'],
            'values' => ['GET', 'HEAD'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    /**
     * GET /site/preferences
     */
    public function actionIndex()
    {
        $this->requireIndexAccess();

        $preferences = Preference::find()
            ->alias('p')
            ->innerJoinWith('setting s')
            ->where(['p.id_user' => Yii::$app->user->id, 's.is_preference' => 1])
            ->orderBy(['p.id_preference' => SORT_ASC])
            ->all();

        return array_map([$this, 'serialize'], $preferences);
    }

    /**
     * GET /site/preferences/{id}
     */
    public function actionView($id)
    {
        $this->requireIndexAccess();

        return $this->serialize($this->findOwn($id));
    }

    /**
     * GET /site/preferences/values?names=a,b
     * Effective values (preference or site default) of preference settings. Other settings are left out.
     * Password-type settings return only the user's own preference, never the site default.
     */
    public function actionValues($names = null)
    {
        $this->requireIndexAccess();

        $query = Setting::find()->where(['is_preference' => 1]);
        if ($names !== null && $names !== '') {
            $list = is_array($names) ? $names : explode(',', (string) $names);
            $query->andWhere(['name' => array_values(array_map('trim', array_filter($list, 'is_string')))]);
        }

        $result = [];
        foreach ($query->orderBy(['id' => SORT_ASC])->all() as $setting) {
            if ((int) $setting->type === Form::TYPE_INPUTPASSWORD) {
                $own = SettingComponent::getSetting($setting->name);
                $result[$setting->name] = $own instanceof Preference ? $own->value : null;
                continue;
            }
            $result[$setting->name] = Yii::$app->setting->getValue($setting->name);
        }

        return $result;
    }

    /**
     * POST /site/preferences
     * body: { setting_name | id_setting, id_workspace?, value }
     * Creates the preference or updates the existing one for the same setting and workspace.
     */
    public function actionCreate()
    {
        $body = Yii::$app->request->bodyParams;
        $setting = $this->resolveSetting($body);
        $idWorkspace = $this->resolveWorkspace($body);

        if (!array_key_exists('value', $body)) {
            throw new BadRequestHttpException('value is required.');
        }

        $model = Preference::findOne([
            'id_user' => Yii::$app->user->id,
            'id_setting' => $setting->id,
            'id_workspace' => $idWorkspace,
        ]);
        $isNew = $model === null;
        if ($isNew) {
            $model = new Preference([
                'id_user' => Yii::$app->user->id,
                'id_setting' => $setting->id,
                'id_workspace' => $idWorkspace,
            ]);
        }
        $this->requireUpdateAccess($model);

        $value = $this->normalizeValue($setting, $body['value']);
        if (is_array($value)) {
            Yii::$app->response->statusCode = 422;
            return $value;
        }
        $model->value = $value;

        if (!$model->save()) {
            Yii::$app->response->statusCode = 422;
            return $model->errors;
        }

        Yii::$app->response->statusCode = $isNew ? 201 : 200;
        return $this->serialize($model);
    }

    /**
     * PUT /site/preferences/{id}
     * body: { value }. Only the value changes.
     */
    public function actionUpdate($id)
    {
        $model = $this->findOwn($id);
        $this->requireUpdateAccess($model);

        $body = Yii::$app->request->bodyParams;
        if (!array_key_exists('value', $body)) {
            throw new BadRequestHttpException('value is required.');
        }

        $value = $this->normalizeValue($model->setting, $body['value']);
        if (is_array($value)) {
            Yii::$app->response->statusCode = 422;
            return $value;
        }
        $model->value = $value;

        if (!$model->save()) {
            Yii::$app->response->statusCode = 422;
            return $model->errors;
        }

        return $this->serialize($model);
    }

    /**
     * DELETE /site/preferences/{id}
     * The site default (or the global preference) applies again.
     */
    public function actionDelete($id)
    {
        $model = $this->findOwn($id);
        $this->requireUpdateAccess($model);

        Preference::deleteAll([
            'id_user' => $model->id_user,
            'id_setting' => $model->id_setting,
            'id_workspace' => $model->id_workspace,
        ]);
        Yii::$app->response->statusCode = 204;
    }

    private function requireIndexAccess()
    {
        if (!Yii::$app->user->can('siteApiPreferenceIndex') && !Yii::$app->user->can('siteApiPreferenceIndexOwn')) {
            throw new ForbiddenHttpException('You are not allowed to access this page.');
        }
    }

    private function requireUpdateAccess(Preference $model)
    {
        if (
            !Yii::$app->user->can('siteApiPreferenceUpdate')
            && !Yii::$app->user->can('siteApiPreferenceUpdateOwn', ['model' => $model])
        ) {
            throw new ForbiddenHttpException('You are not allowed to access this page.');
        }
    }

    private function findOwn($id)
    {
        $model = Preference::find()
            ->alias('p')
            ->innerJoinWith('setting s')
            ->where(['p.id_preference' => (int) $id, 'p.id_user' => Yii::$app->user->id, 's.is_preference' => 1])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Preference not found.');
        }

        return $model;
    }

    private function resolveSetting(array $body)
    {
        if (!empty($body['setting_name']) && is_string($body['setting_name'])) {
            $condition = ['name' => $body['setting_name']];
        } elseif (!empty($body['id_setting']) && is_numeric($body['id_setting'])) {
            $condition = ['id' => (int) $body['id_setting']];
        } else {
            throw new BadRequestHttpException('setting_name or id_setting is required.');
        }

        $setting = Setting::findOne($condition);
        if ($setting === null) {
            throw new NotFoundHttpException('Setting not found.');
        }
        if ((int) $setting->is_preference !== 1) {
            throw new ForbiddenHttpException('This setting cannot be changed as a preference.');
        }

        return $setting;
    }

    /**
     * Empty or missing id_workspace means a global preference. Any other value must be a workspace
     * the user is a member of.
     */
    private function resolveWorkspace(array $body)
    {
        $idWorkspace = $body['id_workspace'] ?? null;
        if ($idWorkspace === null || $idWorkspace === '') {
            return null;
        }
        if (!is_numeric($idWorkspace) || (int) $idWorkspace <= 0) {
            throw new BadRequestHttpException('id_workspace is invalid.');
        }
        $idWorkspace = (int) $idWorkspace;
        if (!Yii::$app->workspace->isMember($idWorkspace, Yii::$app->user->id)) {
            throw new ForbiddenHttpException('You are not a member of this workspace.');
        }

        return $idWorkspace;
    }

    /**
     * Validates the value with the same rules as the preference page. Returns the value to store,
     * or the validation errors as an array.
     */
    private function normalizeValue(Setting $setting, $value)
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        $valueModel = new SettingValue();
        $valueModel->scenario = $setting->type;
        $valueModel->value = $value;
        if ($value === null || !$valueModel->validate()) {
            return ['value' => $value === null ? ['Value is required.'] : $valueModel->getErrors('value')];
        }

        $isMultiple = in_array((int) $setting->type, SettingValue::getScenarios()['multiple'], true);
        if (!$isMultiple && is_string($value) && is_array(json_decode($value, true))) {
            return ['value' => ['Value is invalid.']];
        }

        $options = $this->allowedOptions($setting);
        if ($options !== null) {
            foreach ((array) $valueModel->value as $item) {
                if (!in_array((string) $item, $options, true)) {
                    return ['value' => ['Value is not one of the allowed options.']];
                }
            }
        }

        return is_array($valueModel->value) ? json_encode($valueModel->value) : (string) $valueModel->value;
    }

    /**
     * Option keys of a list-type setting (radio list, list box, dropdown), resolved the same way as the
     * preference form: a fixed key => label map, a model query or a static method. Null for other types.
     */
    private function allowedOptions(Setting $setting)
    {
        $listTypes = [Form::TYPE_RADIOLIST, Form::TYPE_LISTBOX, Form::TYPE_DROPDOWNLIST, Form::TYPE_CHECKBOXLIST];
        if (!in_array((int) $setting->type, $listTypes, true)) {
            return null;
        }

        $config = json_decode((string) $setting->config, true);
        if (!is_array($config)) {
            return [];
        }

        if (isset($config['model']['class'], $config['model']['map']['key'])) {
            $class = $config['model']['class'];
            $rows = $class::find()->where($config['model']['where'] ?? [])->all();
            return array_map('strval', ArrayHelper::getColumn($rows, $config['model']['map']['key']));
        }

        if (isset($config['method']['class'], $config['method']['name'], $config['method']['map']['key'])) {
            $class = $config['method']['class'];
            $method = $config['method']['name'];
            $rows = $class::$method($config['method']['params'] ?? []);
            return array_map('strval', ArrayHelper::getColumn($rows, $config['method']['map']['key']));
        }

        unset($config['hint'], $config['placeholder'], $config['help']);
        return array_map('strval', array_keys($config));
    }

    private function serialize(Preference $model)
    {
        return [
            'id_preference' => (int) $model->id_preference,
            'id_setting' => (int) $model->id_setting,
            'id_workspace' => $model->id_workspace === null ? null : (int) $model->id_workspace,
            'setting_name' => $model->setting->name ?? null,
            'value' => $model->value,
        ];
    }
}
