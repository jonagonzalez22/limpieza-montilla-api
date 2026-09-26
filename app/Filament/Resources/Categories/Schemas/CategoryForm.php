<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
  public static function configure(Schema $schema): Schema
  {
    return $schema
      ->components([
        TextInput::make('name')
          ->label('Nombre')
          ->required()
          ->maxLength(255)
          ->autofocus(),
        Textarea::make('description')
          ->label('Descripción')
          ->nullable()
          ->maxLength(500)
          ->rows(3),
        TextInput::make('sort_order')
          ->label('Orden')
          ->integer()
          ->minValue(0)
          ->default(0)
          ->required(),
        Toggle::make('is_active')
          ->label('Activa')
          ->default(true),
      ]);
  }
}
