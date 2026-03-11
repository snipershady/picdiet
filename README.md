# PicDiet

[![Latest Version](https://img.shields.io/packagist/v/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![Total Downloads](https://img.shields.io/packagist/dt/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![Monthly Downloads](https://img.shields.io/packagist/dm/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![PHP Version](https://img.shields.io/packagist/php-v/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![License](https://img.shields.io/packagist/l/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
#[![Tests](https://github.com/snipershady/picdiet/actions/workflows/tests.yml/badge.svg)](https://github.com/snipershady/picdiet/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/snipershady/picdiet/branch/main/graph/badge.svg)](https://codecov.io/gh/snipershady/picdiet)

Lightweight PHP library for compressing and converting images to **WebP** or **JPEG** format, with automatic resizing while preserving the aspect ratio.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | >= 8.2  |
| ext-gd      | *       |

---

## Installation

```bash
composer require snipershady/picdiet
```

---

## Quick Start

```php
use PicDiet\Service\ImageCompressorService;
use PicDiet\Enum\ImageFormatEnum;

$service = new ImageCompressorService();
$response = $service->compress('/path/to/image.jpg');

if ($response->success) {
    echo $response->path;               // /path/to/image_compressed.webp
    echo $response->compressedFileName; // image_compressed.webp
    echo $response->outputDirectory;    // /path/to
} else {
    echo $response->error;
}
```

---

## API Reference

### `ImageCompressorService::compress()`

```php
public function compress(
    string $sourcePath,
    ImageFormatEnum $format = ImageFormatEnum::WEBP,
    int $maxWidth = 1920,
    int $maxHeight = 1080,
    ?int $quality = null,
): CompressionResponse
```

| Parameter     | Type              | Default                 | Description                                           |
|---------------|-------------------|-------------------------|-------------------------------------------------------|
| `$sourcePath` | `string`          | —                       | Absolute path to the source image                     |
| `$format`     | `ImageFormatEnum` | `ImageFormatEnum::WEBP` | Output format (`WEBP` or `JPG`)                       |
| `$maxWidth`   | `int`             | `1920`                  | Maximum output width in pixels                        |
| `$maxHeight`  | `int`             | `1080`                  | Maximum output height in pixels                       |
| `$quality`    | `?int`            | `85`                    | Compression quality from `0` (worst) to `100` (best)  |

**Supported input formats:** JPEG, PNG, GIF, WebP.

**Resize behaviour:** if the source image is smaller than `$maxWidth` × `$maxHeight` it is never upscaled. If it is larger, it is scaled down proportionally to fit within the given bounds.

**Output file:** the compressed file is saved in the same directory as the source file with the suffix `_compressed` appended to the original filename.

```
/images/photo.jpg  →  /images/photo_compressed.webp
```

---

### `ImageFormatEnum`

```php
use PicDiet\Enum\ImageFormatEnum;

ImageFormatEnum::WEBP  // value: 'webp'
ImageFormatEnum::JPG   // value: 'jpg'
```

---

### `CompressionResponse`

All properties are `readonly`.

| Property             | Type              | Description                                                    |
|----------------------|-------------------|----------------------------------------------------------------|
| `success`            | `bool`            | `true` if compression succeeded                                |
| `path`               | `?string`         | Full absolute path to the compressed file                      |
| `compressedFileName` | `?string`         | Filename of the compressed file (e.g. `photo_compressed.webp`) |
| `outputDirectory`    | `?string`         | Directory where the compressed file was saved                  |
| `originalSize`       | `int`             | Size of the source file in bytes                               |
| `compressedSize`     | `int`             | Size of the compressed file in bytes                           |
| `format`             | `ImageFormatEnum` | Format actually used for compression                           |
| `error`              | `?string`         | Error message when `success` is `false`, otherwise `null`      |

---

## Usage Examples

### Convert to WebP (default)

```php
use PicDiet\Service\ImageCompressorService;

$service = new ImageCompressorService();
$response = $service->compress('/var/www/uploads/photo.jpg');

if ($response->success) {
    printf(
        "Saved %d bytes (%.1f%% reduction)\n",
        $response->originalSize - $response->compressedSize,
        (1 - $response->compressedSize / $response->originalSize) * 100,
    );
}
```

### Convert to JPEG

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorService;

$service = new ImageCompressorService();
$response = $service->compress('/var/www/uploads/photo.png', ImageFormatEnum::JPG);
```

### Custom dimensions and quality

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorService;

$service = new ImageCompressorService();
$response = $service->compress(
    sourcePath: '/var/www/uploads/photo.jpg',
    format: ImageFormatEnum::WEBP,
    maxWidth: 1280,
    maxHeight: 720,
    quality: 75,
);
```

### Error handling

```php
use PicDiet\Service\ImageCompressorService;

$service = new ImageCompressorService();
$response = $service->compress('/var/www/uploads/photo.jpg');

if (!$response->success) {
    error_log('PicDiet compression failed: ' . $response->error);
    return;
}

// Safe to use $response->path, $response->compressedFileName, etc.
```

### Symfony integration

Register the service in the container (if autowiring is not used):

```yaml
# config/services.yaml
services:
    PicDiet\Service\ImageCompressorService: ~
```

Use it in a controller:

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorService;
use Symfony\Component\HttpFoundation\Request;

class ImageController
{
    public function __construct(
        private readonly ImageCompressorService $compressor,
    ) {}

    public function upload(Request $request): void
    {
        $file = $request->files->get('image');
        $uploadPath = '/var/www/uploads/' . $file->getClientOriginalName();
        $file->move('/var/www/uploads', $file->getClientOriginalName());

        $response = $this->compressor->compress(
            sourcePath: $uploadPath,
            format: ImageFormatEnum::WEBP,
        );

        if (!$response->success) {
            throw new \RuntimeException($response->error);
        }

        // $response->path → path to the compressed file
    }
}
```

### Laravel integration

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorService;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function upload(Request $request): void
    {
        $path = $request->file('image')->store('uploads');
        $fullPath = storage_path('app/' . $path);

        $compressor = new ImageCompressorService();
        $response = $compressor->compress(
            sourcePath: $fullPath,
            format: ImageFormatEnum::WEBP,
        );

        if (!$response->success) {
            abort(500, $response->error);
        }

        // $response->path → path to the compressed file
    }
}
```

---

## Development

Clone the repository and install dependencies:

```bash
git clone https://github.com/snipershady/picdiet.git
cd picdiet
composer install
```

### Available commands

| Command                  | Description                                       |
|--------------------------|---------------------------------------------------|
| `composer test`          | Run the PHPUnit test suite                        |
| `composer phpstan`       | Run PHPStan static analysis                       |
| `composer cs-fix`        | Fix code style with PHP-CS-Fixer                  |
| `composer cs-check`      | Check code style without applying changes         |
| `composer rector`        | Run Rector refactoring                            |
| `composer rector-dry`    | Preview Rector changes without applying them      |
| `composer quality`       | Run all quality tools (Rector + PHP-CS-Fixer)     |
| `composer quality-check` | Check all quality rules without applying changes  |

---

## License

Released under the [GPL-2.0-only](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) license.

---

## Author

**Stefano Perrini** — [spinfo.it](https://www.spinfo.it) 
