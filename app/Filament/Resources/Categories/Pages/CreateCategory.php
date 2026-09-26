<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCategory extends CreateRecord
{
  protected static string $resource = CategoryResource::class;

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  protected function mutateFormDataBeforeCreate(array $data): array
  {
    $slug = Str::slug($data['name']);

    if (Category::query()->where('slug', $slug)->exists()) {
      throw ValidationException::withMessages([
        'data.name' => 'Ya existe una categoría con un nombre igual o equivalente.',
      ]);
    }

    $data['slug'] = $slug;

    return $data;
  }

  protected function getRedirectUrl(): string
  {
    return static::getResource()::getUrl('index');
  }
}
