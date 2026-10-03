<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_category_with_an_active_product_is_returned(): void
    {
        $category = $this->createCategory();
        $this->createProduct($category);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->getKey());
    }

    public function test_an_inactive_category_with_an_active_product_is_not_returned(): void
    {
        $category = $this->createCategory(isActive: false);
        $this->createProduct($category);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_active_category_without_products_is_not_returned(): void
    {
        $this->createCategory();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_active_category_with_only_inactive_products_is_not_returned(): void
    {
        $category = $this->createCategory();
        $this->createProduct($category, isActive: false);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_categories_are_sorted_by_sort_order_and_then_name(): void
    {
        $laterCategory = $this->createCategory(name: 'Zeta', sortOrder: 1);
        $firstAlphabeticalCategory = $this->createCategory(name: 'Alfa', sortOrder: 0);
        $secondAlphabeticalCategory = $this->createCategory(name: 'Beta', sortOrder: 0);

        $this->createProduct($laterCategory);
        $this->createProduct($firstAlphabeticalCategory);
        $this->createProduct($secondAlphabeticalCategory);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstAlphabeticalCategory->getKey())
            ->assertJsonPath('data.1.id', $secondAlphabeticalCategory->getKey())
            ->assertJsonPath('data.2.id', $laterCategory->getKey());
    }

    public function test_the_response_contains_only_public_category_fields(): void
    {
        $category = $this->createCategory(description: 'Productos de papel');
        $this->createProduct($category);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ]],
            ]);
    }

    private function createCategory(
        string $name = 'Papel',
        int $sortOrder = 0,
        bool $isActive = true,
        ?string $description = null,
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.fake()->unique()->numerify('###'))->toString(),
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);
    }

    private function createProduct(Category $category, bool $isActive = true): Product
    {
        $code = fake()->unique()->bothify('CAT-###??');

        return Product::create([
            'category_id' => $category->getKey(),
            'code' => $code,
            'name' => 'Producto '.$code,
            'slug' => str($code)->lower()->replace('_', '-')->toString(),
            'description' => null,
            'price' => null,
            'show_price' => false,
            'is_featured' => false,
            'is_active' => $isActive,
            'source_system' => null,
            'source_id' => null,
        ]);
    }
}
