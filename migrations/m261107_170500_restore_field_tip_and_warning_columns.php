<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * Restores Craft 5.12 field columns that were removed by a failed DB restore.
 */
class m261107_170500_restore_field_tip_and_warning_columns extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::FIELDS, 'tip')) {
            $this->addColumn(Table::FIELDS, 'tip', $this->text()->after('instructions'));
        }

        if (!$this->db->columnExists(Table::FIELDS, 'warning')) {
            $this->addColumn(Table::FIELDS, 'warning', $this->text()->after('tip'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        // These are core Craft 5.12 columns and must not be removed.
        return false;
    }
}
