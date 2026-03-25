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

**Output filename:** the compressed file is saved with the suffix `_compressed` appended to the original filename and the extension replaced with the chosen format.

```
/images/photo.jpg  →  /images/photo_compressed.webp
/images/banner.png →  /images/banner_compressed.jpg
```

---

### `CompressionResponse`

All properties are `readonly`. The constructor is private: instances are only created via the named constructors `CompressionResponse::success()` and `CompressionResponse::failure()` inside the service layer.

| Property | Type | Description |
|---|---|---|
| `$success` | `bool` | `true` if compression succeeded |
| `$path` | `string\|null` | Absolute path to the compressed file (`null` on failure) |
| `$error` | `string\|null` | Error message when `$success` is `false`, otherwise `null` |
| `$originalSize` | `int` | Source file size in bytes (`0` on early failure before the file is read) |
| `$compressedSize` | `int` | Output file size in bytes (`0` on failure) |
| `$format` | `ImageFormatEnum\|null` | Format used for the output. Guaranteed non-null on success. On failure, `null` when the error occurs before the image is loaded (e.g. file not found); otherwise carries the intended format |
| `$compressedFileName` | `string\|null` | File name of the compressed file (`null` on failure) |
| `$outputDirectory` | `string\|null` | Directory where the compressed file was saved (`null` on failure) |

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

## GD vs Imagick: choosing the right backend

Both backends implement the same interface and produce equivalent results in the most common scenarios (JPEG/PNG/WebP in, WebP/JPEG out). The choice depends on your infrastructure constraints and the quality bar you need to meet.

### When to choose GD

- **Zero-friction setup.** `ext-gd` ships with virtually every PHP distribution and most shared hosting plans. No system packages to install, no ImageMagick version to manage.
- **Minimal memory footprint.** GD keeps everything in the PHP heap: predictable memory usage, easy to tune with `memory_limit`.
- **Enough for everyday web images.** Thumbnail generation, upload pipelines, and CMS image processing rarely require the extra quality headroom that Imagick provides. GD handles these workloads well.
- **Shared or managed hosting.** When you cannot install system packages (e.g. plain cPanel hosting), GD is the only viable option.

### When to choose Imagick

- **Higher resampling quality.** Imagick uses the Lanczos filter by default, which produces noticeably sharper results than GD's bicubic when downsizing to thumbnails or aggressive resolutions (e.g. 1200 px → 120 px). The difference is visible on product images, portfolio photos, and print-ready assets.
- **Automatic EXIF stripping.** `stripImage()` removes all metadata (GPS coordinates, camera model, author, etc.) in a single call. With GD, metadata is silently preserved unless you handle it yourself. This matters for privacy-sensitive applications (medical imaging, user photo uploads).
- **Animated GIFs.** GD can only read and write the first frame, silently discarding the animation. Imagick preserves all frames. If your application accepts GIF uploads, Imagick is the correct choice.
- **Wider input format support.** Imagick can decode AVIF, HEIC/HEIF (iPhone default format), TIFF, BMP, and many others out of the box, provided the corresponding system libraries are present. GD is limited to JPEG, PNG, WebP, and GIF.
- **Large image processing.** ImageMagick delegates heavy work to a native process, avoiding PHP's memory ceiling. Processing a 50 MP RAW-quality TIFF with GD will exhaust `memory_limit`; Imagick will not.
- **Professional / editorial workflows.** Photo agencies, print pipelines, and e-commerce platforms with high visual standards typically require Lanczos-quality resampling and reliable EXIF removal. Imagick is the appropriate backend for these contexts.

### Summary table

| | GD | Imagick |
|---|---|---|
| Availability | Built into most PHP packages | Requires `imagemagick` system package |
| Installation complexity | None | `apt install imagemagick php-imagick` |
| Resampling algorithm | Bicubic | Lanczos (sharper results) |
| EXIF / metadata stripping | No | Yes (automatic) |
| Memory model | PHP heap | Delegated to ImageMagick process |
| Animated GIF input | First frame only | Full animation preserved |
| AVIF / HEIC input | No | Yes (requires system libs) |
| TIFF, BMP input | No | Yes |
| Best suited for | Shared hosting, simple pipelines, cost-sensitive deployments | High-quality image processing, privacy-sensitive apps, wide format support |

> **Rule of thumb:** start with `createBest()`. It automatically uses Imagick when available and falls back to GD otherwise, so your code is portable and improves silently as infrastructure grows.

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
    $saved = $response->originalSize - $response->compressedSize;
    $ratio = (1 - $response->compressedSize / $response->originalSize) * 100;

    printf("Original:   %d bytes\n", $response->originalSize);
    printf("Compressed: %d bytes\n", $response->compressedSize);
    printf("Saved:      %d bytes (%.1f%% reduction)\n", $saved, $ratio);
    printf("Output:     %s\n", $response->path);
}
```

---

### Convert PNG to WebP (preserving transparency)

GD and Imagick both handle PNG alpha channels correctly when converting to WebP, which natively supports transparency.

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress('/var/www/uploads/logo.png', ImageFormatEnum::WEBP);

if ($response->success) {
    // logo_compressed.webp — fully transparent pixels preserved
    echo $response->path;
}
```

---

### Convert to JPEG

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Enum\CompressionStrategy;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::factory(CompressionStrategy::GD);
$response = $service->compress('/var/www/uploads/photo.png', ImageFormatEnum::JPG);
```

---

### Resize to a maximum resolution

The image is scaled down proportionally so that neither dimension exceeds the given maximum. Images smaller than the maximum are never upscaled.

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();

// Resize to at most 1280×720, keeping aspect ratio
$response = $service->compress(
    sourcePath: '/var/www/uploads/photo.jpg',
    format:     ImageFormatEnum::WEBP,
    maxWidth:   1280,
    maxHeight:  720,
);

// A 1920×1080 source becomes 1280×720
// A 1920×800 source becomes 1280×533 (ratio preserved)
// A 640×480 source stays 640×480 (no upscaling)
```

---

### Generate a thumbnail

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress(
    sourcePath: '/var/www/uploads/photo.jpg',
    format:     ImageFormatEnum::WEBP,
    maxWidth:   300,
    maxHeight:  300,
    quality:    70,
);
```

---

### Custom output directory

By default the compressed file is saved alongside the source. Pass `outputDirectory` to write it elsewhere. The directory must already exist.

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress(
    sourcePath:      '/var/www/uploads/photo.jpg',
    format:          ImageFormatEnum::WEBP,
    outputDirectory: '/var/www/compressed/',
);

if ($response->success) {
    echo $response->outputDirectory; // /var/www/compressed/
    echo $response->path;            // /var/www/compressed/photo_compressed.webp
}
```

---

### Custom quality

`quality` accepts values from `0` (maximum compression, lowest quality) to `100` (minimum compression, best quality). The default is `85`, which is a good balance for most web use cases.

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();

// High quality — larger file, ideal for print or editorial
$response = $service->compress(
    sourcePath: '/var/www/uploads/photo.jpg',
    format:     ImageFormatEnum::WEBP,
    quality:    95,
);

// Low quality — smallest file, acceptable for previews and thumbnails
$response = $service->compress(
    sourcePath: '/var/www/uploads/photo.jpg',
    format:     ImageFormatEnum::WEBP,
    quality:    40,
);
```

---

### Error handling

`$response->success` is always set. Check it before accessing output properties — they are `null` on failure.

```php
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$response = $service->compress('/var/www/uploads/photo.jpg');

if (!$response->success) {
    // Possible error messages:
    //   'Source file does not exist'          — $format is null, $originalSize is 0
    //   'Failed to read source file size'     — $format is null, $originalSize is 0
    //   'Invalid image file'                  — GD backend; $format and $originalSize are set
    //   'Invalid image file, Exception: ...'  — Imagick backend; $format and $originalSize are set
    //   'Failed to create image resource'     — GD only; unsupported format (e.g. BMP)
    //   'Failed to save compressed image'     — write error; $format and $originalSize are set
    //   'Failed to save compressed image, Exception: ...' — Imagick backend write error
    //   'Failed to read compressed file size' — post-write stat error
    error_log('PicDiet compression failed: ' . $response->error);
    return;
}

// On success: $path, $compressedFileName, $outputDirectory and $format are guaranteed non-null
echo $response->path;
echo $response->compressedFileName;
echo $response->outputDirectory;
echo $response->format->value; // 'webp' or 'jpg'
```

---

### Batch processing a directory

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

$service = ImageCompressorFactory::createBest();
$outputDir = '/var/www/compressed/';

foreach (glob('/var/www/uploads/*.{jpg,jpeg,png}', GLOB_BRACE) as $sourcePath) {
    $response = $service->compress(
        sourcePath:      $sourcePath,
        format:          ImageFormatEnum::WEBP,
        maxWidth:        1920,
        maxHeight:       1080,
        quality:         85,
        outputDirectory: $outputDir,
    );

    if ($response->success) {
        printf("OK  %s → %s (%d bytes saved)\n",
            basename($sourcePath),
            $response->compressedFileName,
            $response->originalSize - $response->compressedSize,
        );
    } else {
        printf("ERR %s: %s\n", basename($sourcePath), $response->error);
    }
}
```

---

## Framework integration

### Symfony

Register the interface in the container and bind it to the factory:

```yaml
# config/services.yaml
services:
    PicDiet\Service\ImageCompressorInterface:
        factory: ['PicDiet\Service\ImageCompressorFactory', 'createBest']
```

Use it in a controller or service:

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ImageUploadController
{
    public function __construct(
        private readonly ImageCompressorInterface $compressor,
    ) {}

    public function __invoke(Request $request): void
    {
        $file = $request->files->get('image');
        $uploadPath = '/var/www/uploads/' . $file->getClientOriginalName();
        $file->move('/var/www/uploads', $file->getClientOriginalName());

        $response = $this->compressor->compress(
            sourcePath:      $uploadPath,
            format:          ImageFormatEnum::WEBP,
            maxWidth:        1920,
            maxHeight:       1080,
            outputDirectory: '/var/www/compressed/',
        );

        if (!$response->success) {
            throw new \RuntimeException($response->error);
        }

        // $response->path → absolute path to the compressed file
    }
}
```

---

### Laravel

Bind the factory in a service provider, then inject it wherever needed:

```php
// app/Providers/AppServiceProvider.php
use PicDiet\Service\ImageCompressorFactory;
use PicDiet\Service\ImageCompressorInterface;

public function register(): void
{
    $this->app->bind(ImageCompressorInterface::class, function () {
        return ImageCompressorFactory::createBest();
    });
}
```

Use it in a controller:

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __construct(
        private readonly ImageCompressorInterface $compressor,
    ) {}

    public function upload(Request $request): void
    {
        $path = $request->file('image')->store('uploads');
        $fullPath = storage_path('app/' . $path);

        $response = $this->compressor->compress(
            sourcePath:      $fullPath,
            format:          ImageFormatEnum::WEBP,
            maxWidth:        1920,
            maxHeight:       1080,
            outputDirectory: storage_path('app/compressed'),
        );

        if (!$response->success) {
            abort(500, $response->error);
        }

        // $response->path → absolute path to the compressed file
    }
}
```

---

## Custom implementation

Implement `ImageCompressorInterface` to plug in your own backend (e.g. a cloud API, libvips, or a mock for testing):

```php
use PicDiet\Dto\CompressionResponse;
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;

class MyCloudCompressor implements ImageCompressorInterface
{
    #[\Override]
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse {
        // Call your cloud API, write the output file, then return:
        return CompressionResponse::success(
            path:             '/tmp/output.webp',
            originalSize:     filesize($sourcePath),
            compressedSize:   1234,
            format:           $format,
            compressedFileName: 'output_compressed.webp',
            outputDirectory:  '/tmp',
        );
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
