<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $schema = 'neoura';
    private string $table = 'about_image_switcher';

    private function tableExists(): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->schema, $this->table]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    public function up(): void
    {
        if ($this->tableExists()) {
            return;
        }

        DB::statement(
            'CREATE TABLE neoura.about_image_switcher (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                file VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        if (!$this->tableExists()) {
            return;
        }

        DB::statement('DROP TABLE neoura.about_image_switcher');
    }
};
