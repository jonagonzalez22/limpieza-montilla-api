<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('images_count')
                    ->label('Imágenes')
                    ->counts('images')
                    ->formatStateUsing(fn ($state): string => "{$state} / 3")
                    ->badge()
                    ->color(fn ($state): string => match ((int) $state) {
                        0 => 'danger',
                        3 => 'success',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('show_price')
                    ->label('Mostrar precio')
                    ->boolean(),
                ToggleColumn::make('is_featured')
                    ->label('Destacado'),
                ToggleColumn::make('is_active')
                    ->label('Activo'),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship(
                        name: 'category',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('is_active', true)
                    )
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
                TernaryFilter::make('is_featured')
                    ->label('Destacado')
                    ->placeholder('Todos')
                    ->trueLabel('Destacados')
                    ->falseLabel('No destacados'),
                TernaryFilter::make('has_images')
                    ->label('Imágenes')
                    ->placeholder('Todos')
                    ->trueLabel('Con imágenes')
                    ->falseLabel('Sin imágenes')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('images'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('images'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
