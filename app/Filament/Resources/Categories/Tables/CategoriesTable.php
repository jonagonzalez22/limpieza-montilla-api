<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
  public static function configure(Table $table): Table
  {
    return $table
      ->columns([
        TextColumn::make('name')
          ->label('Nombre')
          ->searchable()
          ->sortable(),
        TextColumn::make('sort_order')
          ->label('Orden')
          ->sortable(),
        ToggleColumn::make('is_active')
          ->label('Activa'),
      ])
      ->filters([
        TernaryFilter::make('is_active')
          ->label('Activa')
          ->placeholder('Todas')
          ->trueLabel('Activas')
          ->falseLabel('Inactivas'),
      ])
      ->defaultSort('sort_order')
      ->recordActions([
        EditAction::make(),
      ]);
  }
}
