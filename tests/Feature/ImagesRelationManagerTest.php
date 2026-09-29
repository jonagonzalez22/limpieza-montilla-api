<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['email' => null]), 'web');
    }

    public function test_three_images_can_be_created_from_an_empty_product(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);

        $this->createImages($manager, 3);

        $images = $this->imagesFor($product);

        $this->assertCount(3, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertFalse($images[2]->is_primary);
        $this->assertSame(1, $images->where('is_primary', true)->count());
        $this->assertSame([0, 1, 2], $images->pluck('sort_order')->all());
        $this->assertSame([$product->getKey()], $images->pluck('product_id')->unique()->values()->all());

        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_subsequent_multiple_uploads_preserve_existing_primary_and_order(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);

        $this->createImages($manager, 1);
        $this->createImages($manager, 2);

        $images = $this->imagesFor($product);

        $this->assertCount(3, $images);
        $this->assertSame([0, 1, 2], $images->pluck('sort_order')->all());
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertFalse($images[2]->is_primary);
        $this->assertSame(1, $images->where('is_primary', true)->count());
    }

    public function test_maximum_of_three_images_is_enforced_by_guard_and_action_state(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);

        $this->createImages($manager, 2);
        $directory = $this->imageDirectory($product);
        $filesBeforeRejectedUpload = Storage::disk('public')->allFiles($directory);

        $manager->callTableAction('createImages', data: [
            'paths' => $this->fakeImages(2),
        ]);

        $this->assertCount(2, $this->imagesFor($product));
        $this->assertSame($filesBeforeRejectedUpload, Storage::disk('public')->allFiles($directory));

        $manager = $this->mountManager($product);
        $this->createImages($manager, 1);

        $manager->assertTableActionDisabled('createImages');
        $this->assertCount(3, $this->imagesFor($product));
    }

    public function test_failed_file_in_multiple_upload_cleans_previously_stored_files(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $directory = $this->imageDirectory($product);
        $firstFile = UploadedFile::fake()->image('first.jpg');
        $invalidFile = UploadedFile::fake()->create('invalid.jpg', 1, 'image/jpeg');
        $exception = null;

        try {
            $manager->callTableAction('createImages', data: [
                'paths' => [$firstFile, $invalidFile],
            ]);
        } catch (\Throwable $caughtException) {
            $exception = $caughtException;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('imagecreatefromstring()', $exception->getMessage());
        $this->assertCount(0, $this->imagesFor($product));
        $this->assertSame([], Storage::disk('public')->allFiles($directory));
    }

    public function test_failed_edit_update_cleans_new_file_and_keeps_original_file(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 1);

        $image = $this->imagesFor($product)->first();
        $originalPath = $image->path;
        $dispatcher = ProductImage::getEventDispatcher();

        ProductImage::updating(static function (): void {
            throw new \RuntimeException('Simulated ProductImage update failure.');
        });

        $exception = null;

        try {
            $manager->callTableAction('edit', $image, data: [
                'path' => [UploadedFile::fake()->image('replacement.jpg')],
                'alt_text' => $image->alt_text,
                'is_primary' => true,
            ]);

            $this->fail('Se esperaba una excepción al actualizar ProductImage.');
        } catch (\RuntimeException $caughtException) {
            $exception = $caughtException;
        } finally {
            ProductImage::setEventDispatcher($dispatcher);
        }

        $updated = $image->fresh();

        $this->assertNotNull($exception);
        $this->assertSame('Simulated ProductImage update failure.', $exception->getMessage());
        $this->assertSame($originalPath, $updated->path);
        Storage::disk('public')->assertExists($originalPath);
        $this->assertCount(1, Storage::disk('public')->allFiles($this->imageDirectory($product)));
    }

    public function test_editing_a_second_image_as_primary_demotes_the_first(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 2);

        $images = $this->imagesFor($product);
        $second = $images[1];

        $manager->callTableAction('edit', $second, data: [
            'path' => [$second->path],
            'alt_text' => $second->alt_text,
            'is_primary' => true,
        ]);

        $images = $this->imagesFor($product);

        $this->assertFalse($images[0]->is_primary);
        $this->assertTrue($images[1]->is_primary);
        $this->assertSame(1, $images->where('is_primary', true)->count());
        $this->assertSame([0, 1], $images->pluck('sort_order')->all());
        $this->assertCount(2, $images);
    }

    public function test_editing_the_primary_image_as_not_primary_can_leave_no_primary(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 2);

        $images = $this->imagesFor($product);
        $first = $images[0];

        $manager->callTableAction('edit', $first, data: [
            'path' => [$first->path],
            'alt_text' => $first->alt_text,
            'is_primary' => false,
        ]);

        $images = $this->imagesFor($product);

        $this->assertFalse($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertSame(0, $images->where('is_primary', true)->count());
        $this->assertSame([0, 1], $images->pluck('sort_order')->all());
        $this->assertCount(2, $images);
    }

    public function test_editing_only_alt_text_keeps_the_original_file(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 1);

        $image = $this->imagesFor($product)->first();
        $originalPath = $image->path;

        $manager->callTableAction('edit', $image, data: [
            'path' => [$originalPath],
            'alt_text' => 'Rollo de cocina blanco',
            'is_primary' => true,
        ]);

        $updated = $image->fresh();

        $this->assertSame($originalPath, $updated->path);
        $this->assertSame('Rollo de cocina blanco', $updated->alt_text);
        Storage::disk('public')->assertExists($originalPath);
        $this->assertCount(1, Storage::disk('public')->allFiles($this->imageDirectory($product)));
    }

    public function test_replacing_an_image_deletes_the_previous_file(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 1);

        $image = $this->imagesFor($product)->first();
        $oldPath = $image->path;

        $manager->callTableAction('edit', $image, data: [
            'path' => [UploadedFile::fake()->image('replacement.jpg')],
            'alt_text' => $image->alt_text,
            'is_primary' => true,
        ]);

        $updated = $image->fresh();

        $this->assertNotSame($oldPath, $updated->path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->path);
        $this->assertCount(1, $this->imagesFor($product));
    }

    public function test_deleting_one_of_two_images_keeps_the_product_directory(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 2);

        $images = $this->imagesFor($product);
        $deletedPath = $images[0]->path;
        $remainingPath = $images[1]->path;

        $manager->callTableAction('delete', $images[0]);

        $this->assertCount(1, $this->imagesFor($product));
        Storage::disk('public')->assertMissing($deletedPath);
        Storage::disk('public')->assertExists($remainingPath);
        $this->assertTrue(Storage::disk('public')->directoryExists($this->imageDirectory($product)));
    }

    public function test_deleting_the_last_image_removes_the_product_directory(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 1);

        $image = $this->imagesFor($product)->first();
        $path = $image->path;
        $directory = $this->imageDirectory($product);

        $manager->callTableAction('delete', $image);

        $this->assertCount(0, $this->imagesFor($product));
        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(Storage::disk('public')->directoryExists($directory));
    }

    public function test_bulk_deleting_two_images_keeps_the_unselected_file_and_directory(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 3);

        $images = $this->imagesFor($product);
        $selected = $images->take(2);
        $remainingPath = $images[2]->path;

        $manager->callTableBulkAction('delete', $selected);

        $this->assertCount(1, $this->imagesFor($product));
        foreach ($selected as $image) {
            Storage::disk('public')->assertMissing($image->path);
        }
        Storage::disk('public')->assertExists($remainingPath);
        $this->assertTrue(Storage::disk('public')->directoryExists($this->imageDirectory($product)));
    }

    public function test_bulk_deleting_all_images_removes_all_files_and_directory(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 3);

        $images = $this->imagesFor($product);
        $directory = $this->imageDirectory($product);

        $manager->callTableBulkAction('delete', $images);

        $this->assertCount(0, $this->imagesFor($product));
        foreach ($images as $image) {
            Storage::disk('public')->assertMissing($image->path);
        }
        $this->assertFalse(Storage::disk('public')->directoryExists($directory));
    }

    public function test_reordering_images_persists_relative_order_without_changing_primary(): void
    {
        $product = $this->createProduct();
        $manager = $this->mountManager($product);
        $this->createImages($manager, 3);

        $images = $this->imagesFor($product);
        $primaryId = $images[0]->getKey();
        $newOrder = [$images[2]->getKey(), $images[0]->getKey(), $images[1]->getKey()];

        $manager->call('reorderTable', $newOrder);

        $reordered = $this->imagesFor($product)
            ->sortBy('sort_order')
            ->values();

        $this->assertSame($newOrder, $reordered->pluck('id')->all());
        $this->assertTrue($reordered->firstWhere('id', $primaryId)->is_primary);
        $this->assertSame(1, $reordered->where('is_primary', true)->count());
    }

    private function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Papel',
            'slug' => 'papel',
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->getKey(),
            'code' => 'PAP-001',
            'name' => 'Rollo de cocina',
            'slug' => 'rollo-de-cocina-pap-001',
            'description' => null,
            'price' => null,
            'show_price' => false,
            'is_featured' => false,
            'is_active' => true,
            'source_system' => null,
            'source_id' => null,
        ]);
    }

    private function mountManager(Product $product): mixed
    {
        return Livewire::test(ImagesRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ]);
    }

    private function createImages(mixed $manager, int $count): void
    {
        $manager->callTableAction('createImages', data: [
            'paths' => $this->fakeImages($count),
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function fakeImages(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("image-{$index}.jpg"))
            ->all();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    private function imagesFor(Product $product): Collection
    {
        return $product->images()
            ->orderBy('sort_order')
            ->get();
    }

    private function imageDirectory(Product $product): string
    {
        return 'products/'.$product->getKey();
    }
}
