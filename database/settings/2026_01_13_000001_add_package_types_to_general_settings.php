<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.package_types', ['Lite Speed', 'Ultra Speed']);
    }
    
    public function down(): void
    {
        $this->migrator->delete('general.package_types');
    }
};