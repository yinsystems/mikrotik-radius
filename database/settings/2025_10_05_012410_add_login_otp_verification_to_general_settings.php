<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.enable_login_otp_verification', true);
    }

    public function down(): void
    {
        $this->migrator->delete('general.enable_login_otp_verification');
    }
};