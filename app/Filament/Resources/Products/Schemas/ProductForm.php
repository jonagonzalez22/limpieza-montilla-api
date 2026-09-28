<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Category;
use App\Models\Product;

class ProductForm
{
  public static function configure(Schema $schema): Schema
  {
    return $schema
      ->columns(2)
      ->components([
        Select::make('category_id')
          ->label('Categoría')
          ->relationship(
            name: 'category',
            titleAttribute: 'name',
            modifyQueryUsing: function (Builder $query, ?Product $record): Builder {
              return $query->where(function (Builder $query) use ($record): void {
                $query->where('is_active', true);

                if ($record?->category_id) {
                  $query->orWhereKey($record->category_id);
                }
              });
            }
          )
          ->getOptionLabelFromRecordUsing(
            fn(Category $record): string => $record->name
              . ($record->is_active ? '' : ' (inactiva)')
          )
          ->required()
          ->searchable()
          ->preload(),

        TextInput::make('code')
          ->label('Código')
          ->required()
          ->maxLength(100)
          ->unique(ignoreRecord: true)
          ->validationMessages([
            'unique' => 'Ya existe un producto con este código.',
          ]),

        TextInput::make('name')
          ->label('Nombre')
          ->required()
          ->maxLength(255)
          ->autofocus()
          ->columnSpanFull(),

        Textarea::make('description')
          ->label('Descripción')
          ->nullable()
          ->rows(4)
          ->columnSpanFull(),

        TextInput::make('price')
          ->label('Precio')
          ->numeric()
          ->step(0.01)
          ->minValue(0)
          ->rule('decimal:0,2')
          ->nullable()
          ->required(fn(Get $get): bool => (bool) $get('show_price'))
          ->prefix('$'),

        Grid::make(3)
          ->schema([
            Checkbox::make('show_price')
              ->label('Mostrar precio')
              ->default(false)
              ->live()
              ->helperText('Visible en la web pública.'),

            Checkbox::make('is_featured')
              ->label('Destacado')
              ->default(false)
              ->helperText('Mostrar como producto destacado.'),

            Checkbox::make('is_active')
              ->label('Activo')
              ->default(true)
              ->helperText('Disponible en la web pública.'),
          ])
          ->columnSpanFull(),
      ]);
  }
}
