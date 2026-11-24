<?php

use yii\db\Migration;

class m261124_000013_site_preference_api_permissions extends Migration
{
    public function safeUp()
    {
        $auth = Yii::$app->authManager;
        $role = Yii::$app->setting->getValue('site::user_role');
        $user = (isset($role) && $role != '') ? $auth->getRole($role) : $auth->getRole('user');
        if ($user === null) {
            return true;
        }
        $permissionsName = [
            'siteApiPreferenceIndexOwn',
            'siteApiPreferenceUpdateOwn',
        ];
        foreach ($permissionsName as $permissionName) {
            $permission = $auth->getPermission($permissionName);
            if ($permission !== null && !$auth->hasChild($user, $permission)) {
                $auth->addChild($user, $permission);
            }
        }
        return true;
    }

    public function safeDown()
    {
        return true;
    }
}
