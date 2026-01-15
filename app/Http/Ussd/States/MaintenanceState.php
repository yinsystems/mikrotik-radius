<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\State;

class MaintenanceState extends State
{
    protected function beforeRendering(): void
    {
        $maintenanceMessage = $this->record->get('maintenance_message', 'Sorry, our service is temporarily unavailable for maintenance. Please try again later.');
        
        $this->menu->text($maintenanceMessage);
    }

    protected function afterRendering(string $argument): void
    {
        // End the USSD session - no options available during maintenance
        $this->decision->any(function() {
            return null; // This will end the session
        });
    }
}