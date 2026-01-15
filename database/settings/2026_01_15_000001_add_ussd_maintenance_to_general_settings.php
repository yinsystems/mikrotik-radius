<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.ussd_maintenance_mode', false);
        $this->migrator->add('general.ussd_maintenance_message', 'Sorry, our service is temporarily unavailable for maintenance. Please try again later.');
    }
    
    public function down(): void
    {
        $this->migrator->delete('general.ussd_maintenance_mode');
        $this->migrator->delete('general.ussd_maintenance_message');
    }
};