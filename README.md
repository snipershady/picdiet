# PicDiet

[![Latest Version](https://img.shields.io/packagist/v/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![Total Downloads](https://img.shields.io/packagist/dt/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![Monthly Downloads](https://img.shields.io/packagist/dm/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![PHP Version](https://img.shields.io/packagist/php-v/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![License](https://img.shields.io/packagist/l/snipershady/picdiet.svg)](https://packagist.org/packages/snipershady/picdiet)
[![Tests](https://github.com/snipershady/picdiet/actions/workflows/tests.yml/badge.svg)](https://github.com/snipershady/picdiet/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/snipershady/picdiet/branch/main/graph/badge.svg)](https://codecov.io/gh/snipershady/picdiet)

Lightweight PHP library for compressing and converting images to **WebP** or **JPEG** format, with automatic resizing while preserving the aspect ratio.

Supports two backends: **GD** (built-in, zero extra dependencies) and **Imagick** (higher resampling quality, EXIF stripping, animated GIF support, AVIF/HEIC input).

---

## Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | >= 8.3 | |
| ext-gd | * | Required for the GD backend |
| ext-imagick | * | Optional — required only for the Imagick backend |

---

## Installing PHP extensions on Debian / Ubuntu

> Replace `php8.3` with your actual PHP version. Check it with `php -v`.

### ext-gd

```bash
sudo apt update
sudo apt install php8.3-gd
sudo phpenmod gd
sudo systemctl restart php8.3-fpm   # or apache2, nginx, etc.
```

Verify:

```bash
php -m | grep gd
```

---

### ext-imagick

Imagick requires both the PHP extension and the ImageMagick system library.

```bash
sudo apt update
sudo apt install imagemagick php8.3-imagick
sudo phpenmod imagick
sudo systemctl restart php8.3-fpm
```

Verify:

```bash
php -m | grep imagick
```

> **HEIC / HEIF support** (optional): requires `libheif` and a version of ImageMagick compiled with HEIC support.
>
> ```bash
> sudo apt install libheif-dev
> ```

---

## Installation

```bash
composer require snipershady/picdiet
```

---

## Quick start

### Automatic backend selection (recommended)

`createBest()` picks **Imagick** if the extension is loaded, otherwise falls back to **GD**:

```php
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress('/path/to/photo.jpg');

if ($response->success) {
    echo $response->path;           // /path/to/photo_compressed.webp
    echo $response->originalSize;   // bytes
    echo $response->compressedSize; // bytes
} else {
    echo $response->error;
}
```

---

### Explicit backend selection

```php
use PicDiet\Enum\CompressionStrategy;
use PicDiet\Service\ImageCompressorFactory;

// GD backend (always available if ext-gd is installed)
$service = ImageCompressorFactory::factory(CompressionStrategy::GD);

// Imagick backend (throws RuntimeException if ext-imagick is not loaded)
$service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
```

---

### Check backend availability at runtime

```php
use PicDiet\Enum\CompressionStrategy;
use PicDiet\Service\ImageCompressorFactory;

if (ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
    $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
} else {
    $service = ImageCompressorFactory::factory(CompressionStrategy::GD);
}
```

---

## API Reference

### `compress()` — all parameters

```php
$response = $service->compress(
    sourcePath:      '/path/to/image.png',    // required
    format:          ImageFormatEnum::WEBP,   // WEBP (default) or JPG
    maxWidth:        1920,                    // default 1920 px
    maxHeight:       1080,                    // default 1080 px
    quality:         85,                      // 0–100, default 85
    outputDirectory: '/path/to/output/',      // default: same directory as source
);
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$sourcePath` | `string` | — | Absolute path to the source image |
| `$format` | `ImageFormatEnum` | `WEBP` | Output format: `WEBP` or `JPG` |
| `$maxWidth` | `int` | `1920` | Max output width in pixels. The image is never upscaled |
| `$maxHeight` | `int` | `1080` | Max output height in pixels. Aspect ratio is always preserved |
| `$quality` | `int\|null` | `85` | Compression quality 0–100 |
| `$outputDirectory` | `string\|null` | same as source | Directory where the compressed file is written. Must exist and be writable |

**Output filename:** the compressed file is saved with the suffix `_compressed` appended to the original filename.

```
/images/photo.jpg  →  /images/photo_compressed.webp
```

---

### `CompressionResponse`

All properties are `readonly`. Use the named constructors `success()` / `failure()` — the constructor is private.

| Property | Type | Description |
|---|---|---|
| `$success` | `bool` | `true` if compression succeeded |
| `$path` | `string\|null` | Absolute path to the compressed file |
| `$error` | `string\|null` | Error message when `$success` is `false`, otherwise `null` |
| `$originalSize` | `int` | Source file size in bytes |
| `$compressedSize` | `int` | Output file size in bytes |
| `$format` | `ImageFormatEnum` | Format used for the output |
| `$compressedFileName` | `string\|null` | File name of the compressed file |
| `$outputDirectory` | `string\|null` | Directory where the compressed file was saved |

---

### `CompressionStrategy`

```php
use PicDiet\Enum\CompressionStrategy;

CompressionStrategy::GD      // PHP GD extension
CompressionStrategy::IMAGICK // PHP Imagick extension
```

---

### `ImageFormatEnum`

```php
use PicDiet\Enum\ImageFormatEnum;

ImageFormatEnum::WEBP  // value: 'webp'
ImageFormatEnum::JPG   // value: 'jpg'
```

---

## GD vs Imagick

| | GD | Imagick |
|---|---|---|
| Availability | Built into most PHP packages | Requires `imagemagick` system package |
| Resampling quality | Bicubic | Lanczos (sharper on aggressive resize) |
| EXIF stripping | No | Yes (automatic) |
| Memory model | PHP heap | Delegated to ImageMagick process |
| Animated GIF | First frame only | Full animation preserved |
| AVIF / HEIC input | No | Yes |

---

## Supported input formats

| Format | GD | Imagick |
|---|---|---|
| JPEG | yes | yes |
| PNG (with transparency) | yes | yes |
| WebP | yes | yes |
| GIF (first frame only) | yes | yes |
| GIF (animated, all frames) | no | yes |
| AVIF | no | yes |
| HEIC / HEIF | no | yes (requires libheif) |
| TIFF | no | yes |

---

## Usage examples

### Convert to WebP and measure savings

```php
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
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
use PicDiet\Enum\CompressionStrategy;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::factory(CompressionStrategy::GD);
$response = $service->compress('/var/www/uploads/photo.png', ImageFormatEnum::JPG);
```

### Custom dimensions, quality and output directory

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress(
    sourcePath:      '/var/www/uploads/photo.jpg',
    format:          ImageFormatEnum::WEBP,
    maxWidth:        1280,
    maxHeight:       720,
    quality:         75,
    outputDirectory: '/var/www/compressed/',
);
```

### Error handling

```php
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress('/var/www/uploads/photo.jpg');

if (!$response->success) {
    error_log('PicDiet compression failed: ' . $response->error);
    return;
}

// Safe to use $response->path, $response->compressedFileName, etc.
```

---

## Framework integration

### Symfony

Register the interface in the container and bind it to the desired implementation:

```yaml
# config/services.yaml
services:
    PicDiet\Service\ImageCompressorInterface:
        factory: ['PicDiet\Service\ImageCompressorFactory', 'createBest']
```

Use it in a controller:

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;
use Symfony\Component\HttpFoundation\Request;

class ImageController
{
    public function __construct(
        private readonly ImageCompressorInterface $compressor,
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

---

### Laravel

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function upload(Request $request): void
    {
        $path = $request->file('image')->store('uploads');
        $fullPath = storage_path('app/' . $path);

        $service = ImageCompressorFactory::createBest();
        $response = $service->compress(
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

## Custom implementation

Implement `ImageCompressorInterface` to provide your own backend (e.g. cloud API, libvips):

```php
use PicDiet\Dto\CompressionResponse;
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;

class MyCloudCompressor implements ImageCompressorInterface
{
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse {
        // your implementation
    }
}
```

---

## Development

```bash
git clone https://github.com/snipershady/picdiet.git
cd picdiet
composer install
```

| Command | Description |
|---|---|
| `composer test` | Run the PHPUnit test suite |
| `composer phpstan` | Run PHPStan static analysis |
| `composer cs-fix` | Fix code style with PHP-CS-Fixer |
| `composer cs-check` | Check code style without applying changes |
| `composer rector` | Run Rector refactoring |
| `composer rector-dry` | Preview Rector changes without applying them |
| `composer quality` | Run all quality tools (Rector + PHP-CS-Fixer) |
| `composer quality-check` | Check all quality rules without applying changes |

---

## License

Released under the [GPL-2.0-only](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) license.

---

## Author

**Stefano Perrini** — [spinfo.it](https://www.spinfo.it)
