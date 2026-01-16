<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\ProcessPaymentAction;
use App\Http\Ussd\Actions\SelectPackageAction;
use Sparors\Ussd\State;

class ConfirmPackageState extends State
{
    protected function beforeRendering(): void
    {
        $packageName = $this->record->get('selected_package_name');
        $packagePrice = $this->record->get('selected_package_price');
        $packageDuration = $this->record->get('selected_package_duration');
        $packageData = $this->record->get('selected_package_data');
        $packageUsers = $this->record->get('selected_package_users');
        $packageUploadSpeed = $this->record->get('selected_package_upload_speed', 0);
        $packageDownloadSpeed = $this->record->get('selected_package_download_speed', 0);

        // Format price with appropriate decimal places
        $priceDisplay = $packagePrice == floor($packagePrice) ?
            number_format($packagePrice, 0) :
            number_format($packagePrice, 2);

        // Convert kbps to MB/s (divide by 1000 twice: kbps -> kB/s -> MB/s)
        $uploadMBps = $packageUploadSpeed > 0 ? round($packageUploadSpeed / 1000, 1) : 0;
        $downloadMBps = $packageDownloadSpeed > 0 ? round($packageDownloadSpeed / 1000, 1) : 0;

        // Format speed display
        $speedDisplay = '';
        if ($downloadMBps > 0 && $uploadMBps > 0) {
            if ($downloadMBps == $uploadMBps) {
                $speedDisplay = $downloadMBps . 'MB/s';
            } else {
                $speedDisplay = $downloadMBps . '/' . $uploadMBps . 'MB/s';
            }
        }

        $this->menu
            ->line('Confirm Purchase:')
            ->line($packageName)
            ->line('GHS ' . $priceDisplay)
            ->line('Data Limit: ' . $packageData);

        // Add speed line only if speed data is available
        if (!empty($speedDisplay)) {
            $this->menu->line('Speed: ' . $speedDisplay);
        }

        $this->menu
            ->line($packageUsers . ' User(s) Allowed')
            ->lineBreak()
            ->line('1) Pay')
            ->line('0) Back');
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', ProcessPaymentAction::class);
        $this->decision->equal('0', SelectPackageAction::class);
    }
}
