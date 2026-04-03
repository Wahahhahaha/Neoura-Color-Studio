<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $schema = 'neoura';
    private string $table = 'system';

    private function hasColumn(string $column): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->schema, $this->table, $column]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    public function up(): void
    {
        if (!$this->hasColumn('operational_open')) {
            DB::statement('ALTER TABLE neoura.system ADD COLUMN operational_open VARCHAR(5) NULL AFTER systemaddress');
        }

        if (!$this->hasColumn('operational_close')) {
            DB::statement('ALTER TABLE neoura.system ADD COLUMN operational_close VARCHAR(5) NULL AFTER operational_open');
        }

        DB::statement("UPDATE neoura.system SET operational_open = '10:00' WHERE operational_open IS NULL OR operational_open = ''");
        DB::statement("UPDATE neoura.system SET operational_close = '22:00' WHERE operational_close IS NULL OR operational_close = ''");
    }

    public function down(): void
    {
        if ($this->hasColumn('operational_close')) {
            DB::statement('ALTER TABLE neoura.system DROP COLUMN operational_close');
        }

        if ($this->hasColumn('operational_open')) {
            DB::statement('ALTER TABLE neoura.system DROP COLUMN operational_open');
        }
    }
};

