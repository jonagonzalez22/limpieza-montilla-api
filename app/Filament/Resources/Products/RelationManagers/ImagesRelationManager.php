<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductImage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Imágenes';

    private ?string $imagePathBeforeEdit = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->makeImageUpload('path', 'Imagen'),
                TextInput::make('alt_text')
                    ->label('Descripción de la imagen')
                    ->nullable()
                    ->maxLength(255)
                    ->helperText('Descripción breve de la imagen para accesibilidad y buscadores.'),
                Checkbox::make('is_primary')
                    ->label('Imagen principal')
                    ->default(false)
                    ->helperText('Será la imagen principal del producto en el catálogo.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('path')
                    ->label('Imagen')
                    ->disk('public')
                    ->imageSize(64),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('createImages')
                    ->label('Agregar imágenes')
                    ->modalHeading('Agregar imágenes')
                    ->disabled(fn (): bool => $this->getOwnerRecord()->images()->count() >= 3)
                    ->tooltip(fn (): ?string => $this->getOwnerRecord()->images()->count() >= 3
                        ? 'Máximo 3 imágenes por producto.'
                        : null)
                    ->schema([
                        $this->makeImageUpload('paths', 'Imágenes', multiple: true)
                            ->minFiles(1)
                            ->maxFiles(fn (): int => max(
                                0,
                                3 - $this->getOwnerRecord()->images()->count(),
                            ))
                            ->helperText(fn (): string => match (max(
                                0,
                                3 - $this->getOwnerRecord()->images()->count(),
                            )) {
                                3 => 'Podés cargar hasta 3 imágenes.',
                                2 => 'Podés cargar hasta 2 imágenes más.',
                                1 => 'Podés cargar 1 imagen más.',
                                default => 'No hay más imágenes disponibles.',
                            })
                            ->validationMessages([
                                'max' => fn (): string => match (max(
                                    0,
                                    3 - $this->getOwnerRecord()->images()->count(),
                                )) {
                                    3 => 'Podés cargar como máximo 3 imágenes.',
                                    2 => 'Podés cargar como máximo 2 imágenes.',
                                    1 => 'Podés cargar como máximo 1 imagen.',
                                    default => 'No podés cargar más imágenes.',
                                },
                            ]),
                    ])
                    ->databaseTransaction()
                    ->beforeFormValidated(function (Action $action): void {
                        $existingCount = $this->getOwnerRecord()->images()->count();
                        $rawState = $action->getRawData();
                        $selectedFiles = is_array($rawState)
                            ? Arr::wrap($rawState['paths'] ?? [])
                            : [];
                        $selectedCount = count(array_filter(
                            $selectedFiles,
                            static fn (mixed $file): bool => filled($file),
                        ));

                        if (($existingCount + $selectedCount) <= 3) {
                            return;
                        }

                        Notification::make()
                            ->title('Este producto admite un máximo de 3 imágenes.')
                            ->danger()
                            ->send();

                        $action->halt();
                    })
                    ->action(function (array $data): void {
                        $paths = Arr::wrap($data['paths'] ?? []);

                        if ($paths === []) {
                            return;
                        }

                        $images = $this->getOwnerRecord()->images();
                        $hadImages = $images->exists();
                        $maxSortOrder = $images->max('sort_order');
                        $nextSortOrder = $maxSortOrder === null
                            ? 0
                            : ((int) $maxSortOrder + 1);
                        $storedPaths = array_values(array_filter(
                            $paths,
                            static fn (mixed $path): bool => is_string($path) && filled($path),
                        ));

                        try {
                            foreach ($storedPaths as $index => $path) {
                                $images->create([
                                    'path' => $path,
                                    'sort_order' => $nextSortOrder++,
                                    'is_primary' => ! $hadImages && $index === 0,
                                ]);
                            }
                        } catch (Throwable $exception) {
                            Storage::disk('public')->delete($storedPaths);

                            throw $exception;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function (ProductImage $record): void {
                        $this->imagePathBeforeEdit = $record->path;
                    })
                    ->using(function (ProductImage $record, array $data): ProductImage {
                        $oldPath = $this->imagePathBeforeEdit;
                        $newPath = $data['path'] ?? $record->path;

                        try {
                            $record->update($data);

                            return $record;
                        } catch (Throwable $exception) {
                            if (filled($newPath) && $newPath !== $oldPath) {
                                Storage::disk('public')->delete($newPath);
                            }

                            throw $exception;
                        }
                    })
                    ->after(function (ProductImage $record): void {
                        $this->clearOtherPrimaryImages($record);

                        $oldPath = $this->imagePathBeforeEdit;
                        $this->imagePathBeforeEdit = null;

                        if (blank($oldPath) || $oldPath === $record->path) {
                            return;
                        }

                        Storage::disk('public')->delete($oldPath);
                    }),
                DeleteAction::make()
                    ->after(function (ProductImage $record): void {
                        $disk = Storage::disk('public');

                        $disk->delete($record->path);

                        if (! $this->getOwnerRecord()->images()->exists()) {
                            $disk->deleteDirectory(
                                'products/'.$this->getOwnerRecord()->getKey(),
                            );
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (Collection $records): void {
                            $disk = Storage::disk('public');

                            $paths = $records
                                ->pluck('path')
                                ->filter()
                                ->values()
                                ->all();

                            if ($paths !== []) {
                                $disk->delete($paths);
                            }

                            if (! $this->getOwnerRecord()->images()->exists()) {
                                $disk->deleteDirectory(
                                    'products/'.$this->getOwnerRecord()->getKey(),
                                );
                            }
                        }),
                ]),
            ]);
    }

    private function makeImageUpload(string $name, string $label, bool $multiple = false): FileUpload
    {
        $storedPaths = [];

        return FileUpload::make($name)
            ->label($label)
            ->required()
            ->multiple($multiple)
            ->disk('public')
            ->visibility('public')
            ->directory(fn (): string => "products/{$this->getOwnerRecord()->getKey()}")
            ->acceptedFileTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ])
            ->maxSize(5120)
            ->previewable()
            ->openable()
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth('1600')
            ->automaticallyResizeImagesToHeight('1600')
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->preventFilePathTampering()
            ->saveUploadedFileUsing(function (
                BaseFileUpload $component,
                TemporaryUploadedFile $file,
            ) use (&$storedPaths): ?string {
                try {
                    $path = $this->saveImageAsWebp($component, $file);

                    if ($path === null) {
                        throw new \RuntimeException('La imagen no pudo almacenarse correctamente.');
                    }

                    $storedPaths[] = $path;

                    return $path;
                } catch (Throwable $exception) {
                    if ($storedPaths !== []) {
                        Storage::disk('public')->delete($storedPaths);
                        $storedPaths = [];
                    }

                    throw $exception;
                }
            });
    }

    private function saveImageAsWebp(
        BaseFileUpload $component,
        TemporaryUploadedFile $file,
    ): ?string {
        try {
            if (! $file->exists()) {
                return null;
            }
        } catch (UnableToCheckFileExistence $exception) {
            return null;
        }

        $contents = $file->get();
        $image = is_string($contents) ? imagecreatefromstring($contents) : false;

        if ($image === false) {
            throw new \RuntimeException('La imagen no pudo procesarse.');
        }

        $bufferStarted = false;

        try {
            if (function_exists('imagepalettetotruecolor') && function_exists('imageistruecolor') && ! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $bufferStarted = true;

            if (! imagewebp($image, null, 82)) {
                throw new \RuntimeException('La imagen no pudo convertirse a WebP.');
            }

            $contents = ob_get_clean();
            $bufferStarted = false;

            if (! is_string($contents)) {
                throw new \RuntimeException('La imagen WebP no pudo generarse.');
            }

            $filename = Str::ulid().'.webp';
            $path = trim($component->getDirectory().'/'.$filename, '/');
            $disk = $component->getDisk();

            if (! $disk->put($path, $contents, ['visibility' => $component->getVisibility()])) {
                throw new \RuntimeException('La imagen WebP no pudo almacenarse.');
            }

            return $path;
        } finally {
            if ($bufferStarted) {
                ob_end_clean();
            }

            imagedestroy($image);
        }
    }

    private function clearOtherPrimaryImages(ProductImage $record): void
    {
        if (! $record->is_primary) {
            return;
        }

        $record->product
            ->images()
            ->where('id', '!=', $record->getKey())
            ->update(['is_primary' => false]);
    }
}
