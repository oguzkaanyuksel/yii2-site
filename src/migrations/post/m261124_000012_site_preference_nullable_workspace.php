<?php

use yii\db\Migration;
use portalium\site\Module;

class m261124_000012_site_preference_nullable_workspace extends Migration
{
    public function safeUp()
    {
        $table = Module::$tablePrefix . 'preference';
        $column = $this->db->getTableSchema($table, true)->getColumn('id_workspace');
        if ($column !== null && !$column->allowNull) {
            $this->alterColumn($table, 'id_workspace', $this->integer()->null());
        }
    }

    public function safeDown()
    {
        return true;
    }
}
