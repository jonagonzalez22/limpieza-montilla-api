<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
  'name',
  'slug',
  'description',
  'sort_order',
  'is_active',
])]
class Category extends Model
{
  use HasUuids;

  /**
   * Get the products in this category.
   */
  public function products(): HasMany
  {
    return $this->hasMany(Product::class);
  }

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'sort_order' => 'integer',
      'is_active' => 'boolean',
    ];
  }
}
