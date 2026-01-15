<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageTypeState;
use Sparors\Ussd\Action;

class PrevPackageTypePageAction extends Action
{
    public function run(): string
    {
        $currentPage = $this->record->get('package_types_page', 1);
        $packageTypesPerPage = $this->record->get('package_types_per_page', 3);
        $availableTypes = $this->record->get('available_package_types', []);

        if ($currentPage > 1) {
            $newPage = $currentPage - 1;
            $startIndex = ($newPage - 1) * $packageTypesPerPage;
            $currentPageTypes = array_slice($availableTypes, $startIndex, $packageTypesPerPage);

            $this->record->setMultiple([
                'current_package_types' => $currentPageTypes,
                'package_types_page' => $newPage
            ]);
        }

        return SelectPackageTypeState::class;
    }
}