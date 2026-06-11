<?php

namespace App\Filament\Resources\CongressSubscriptions\Pages;

use App\Filament\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CongressSubscriptions\Widgets\CongressStatsWidget;

class ListCongressSubscriptions extends ListRecords
{
    protected static string $resource = CongressSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CongressStatsWidget::class,
        ];
    }
}
