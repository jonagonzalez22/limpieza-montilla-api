<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Product;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Productos', Product::query()->count())
                ->description('Total cargados')
                ->icon(Heroicon::OutlinedArchiveBox),
            Stat::make('Categorías', Category::query()->count())
                ->description('Total cargadas')
                ->icon(Heroicon::OutlinedTag),
            Stat::make(
                'Sin imágenes',
                Product::query()->whereDoesntHave('images')->count(),
            )
                ->description('Pendientes de fotos')
                ->icon(Heroicon::OutlinedPhoto),
        ];
    }
}
