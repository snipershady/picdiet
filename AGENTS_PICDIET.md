# PicDiet — Integration guide for AI coding agents

> **How to load this file.** This file ships inside the Composer package. From the
> integrating project's `CLAUDE.md` (or `AGENTS.md`), reference it with its real path:
>
> ```
> @vendor/snipershady/picdiet/AGENTS_PICDIET.md
> ```
>
> or copy it to the project root and use `@AGENTS_PICDIET.md`.

PicDiet (`snipershady/picdiet`) is a small, dependency-free PHP library that **compresses,
downscales and converts a single image file on disk to WebP or JPEG**. It has one entry
point (`ImageCompressorInterface::compress()`), two backends (GD, Imagick) and returns a
result DTO instead of throwing for runtime failures.

---

## 1. Facts at a glance

| Item | Value |
|---|---|
| Package | `snipershady/picdiet` |
| Install | `composer require snipershady/picdiet` |
| PHP | `>= 8.4` (uses typed class constants, `#[\Override]`) |
| Required ext | `ext-gd` |
| Optional ext | `ext-imagick` (+ system ImageMagick) |
| Runtime deps | none |
| Namespace / PSR-4 | `PicDiet\` → `src/` |
| License | **GPL-2.0-only** (copyleft — check compatibility before shipping in proprietary/distributed software) |
| Output formats | WebP (`'webp'`), JPEG (`'jpg'`) only |
| I/O model | path in → new file on disk out. No streams, no strings/blobs, no in-memory API |

---

## 2. Public API (complete)

Everything an integrator needs lives in these 5 types. `Dto\Dimensions`, `Dto\ImageInfo`
and `Service\AbstractImageCompressorService` are internal helpers — do not depend on them.

### 2.1 `PicDiet\Service\ImageCompressorFactory` (static)

```php
public static function factory(CompressionStrategy $s = CompressionStrategy::GD): ImageCompressorInterface;
// throws \RuntimeException if the extension for $s is not loaded

public static function createBest(): ImageCompressorInterface;
// Imagick if ext-imagick is loaded, else GD. Never throws.

public static function isAvailable(CompressionStrategy $s): bool;
// extension_loaded('gd' | 'imagick')
```

Always obtain services through the factory. Instantiating
`ImageCompressorImagickService` directly without ext-imagick causes a fatal
"Class Imagick not found" at call time.

### 2.2 `PicDiet\Service\ImageCompressorInterface`

```php
public function compress(
    string $sourcePath,
    ImageFormatEnum $imageFormatEnum = ImageFormatEnum::WEBP,
    int $maxWidth = 1920,
    int $maxHeight = 1080,
    ?int $quality = null,            // null => 85
    ?string $outputDirectory = null, // null => dirname($sourcePath)
): CompressionResponse;
```

Implementations: `ImageCompressorGDService`, `ImageCompressorImagickService`
(both non-final, stateless, safe to share as a singleton service).

### 2.3 `PicDiet\Dto\CompressionResponse` (all `public readonly`, private constructor)

| Property | Type | On success | On failure |
|---|---|---|---|
| `success` | `bool` | `true` | `false` |
| `path` | `?string` | absolute/relative output path (see §4.3) | `null` |
| `error` | `?string` | `null` | message (see §3) |
| `originalSize` | `int` | bytes of source | bytes, or `0` if failure happened before reading the file |
| `compressedSize` | `int` | bytes of output | `0` |
| `format` | `?ImageFormatEnum` | non-null | `null` for "does not exist"/"size" errors, otherwise the requested format |
| `compressedFileName` | `?string` | e.g. `photo_compressed.webp` | `null` |
| `outputDirectory` | `?string` | directory actually used | `null` |

Named constructors (public, usable in custom backends and test doubles):

```php
CompressionResponse::success(string $path, int $originalSize, int $compressedSize,
    ImageFormatEnum $imageFormatEnum, string $compressedFileName, string $outputDirectory): self;
CompressionResponse::failure(string $error, int $originalSize = 0, ?ImageFormatEnum $imageFormatEnum = null): self;
```

PHPStan does not narrow `path`/`format` after checking `success`; use `assert(null !== $r->path)`
or `(string) $r->path` when strict analysis complains.

### 2.4 Enums

```php
enum PicDiet\Enum\ImageFormatEnum: string { case WEBP = 'webp'; case JPG = 'jpg'; }
enum PicDiet\Enum\CompressionStrategy { case GD; case IMAGICK; } // pure enum, no ->value
```

---

## 3. Error contract — two channels, handle both

**A. Exceptions (programming / configuration errors)** — thrown *before* touching the source file:

| Exception | When |
|---|---|
| `\InvalidArgumentException` | `maxWidth <= 0`, `maxHeight <= 0`, `quality` outside `0..100`, `outputDirectory` not existing, `outputDirectory` not writable |
| `\RuntimeException` | `ImageCompressorFactory::factory()` with a missing extension |

**B. `CompressionResponse::failure()` (runtime / data errors)** — never thrown:

| `error` string (exact prefix) | Backend | Cause |
|---|---|---|
| `Source file does not exist` | both | `file_exists()` false |
| `Failed to read source file size` | both | `filesize()` false |
| `Invalid image file` | GD | `getimagesize()` false (not an image) |
| `Invalid image file, Exception: <msg>` | Imagick | `new \Imagick()` threw |
| `Failed to create image resource` | GD | type not JPEG/PNG/GIF/WebP (e.g. BMP, AVIF, TIFF) |
| `Computed dimensions must be positive` | GD | defensive, practically unreachable |
| `Failed to allocate transparent color` | GD | defensive (PNG only) |
| `Failed to save compressed image` | both | encoder returned false |
| `Failed to save compressed image, Exception: <msg>` | Imagick | `writeImage()` threw |
| `Failed to read compressed file size` | both | `filesize()` on output false |

Match errors with `str_starts_with()`, not `===` (Imagick messages carry a suffix containing
the full file path — **do not show `error` verbatim to end users**, it leaks server paths).

Canonical call pattern:

```php
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorInterface;

try {
    $r = $compressor->compress($src, ImageFormatEnum::WEBP, 1920, 1080, 85, $outDir);
} catch (\InvalidArgumentException $e) {
    // bad parameters or unusable output dir: a bug/config issue, not user input
    throw $e;
}

if (!$r->success) {
    $logger->warning('PicDiet failed', ['error' => $r->error, 'src' => $src]);
    // fall back to the original file, or reject the upload
    return null;
}

return $r->path; // string, file exists on disk
```

---

## 4. Behaviour you must know (verified against the code)

### 4.1 Resizing
- Fit-inside box `maxWidth × maxHeight`, aspect ratio preserved, **never upscales**.
- GD: `ratio = min(maxW/w, maxH/h)`, dimensions `round()`ed; resampling bicubic (`imagecopyresampled`).
- Imagick: `thumbnailImage($maxW, $maxH, bestfit: true)` — Lanczos-like; also strips profiles.
- No cropping, no fixed-size output, no "cover" mode. For square thumbnails crop yourself first.
- Defaults (1920×1080) **always apply**: a 4000×4000 photo becomes 1080×1080 even if you only
  wanted re-encoding. Pass large limits (e.g. `PHP_INT_MAX`) to disable resizing.

### 4.2 Output file naming
- `{outputDirectory}/{source filename without extension}_compressed.{webp|jpg}`
- JPEG output always uses extension `.jpg` (never `.jpeg`).
- The source file is **never modified or deleted** — delete it yourself if desired.
- An existing output file with the same name is **silently overwritten**. Collisions happen
  when `photo.jpg` and `photo.png` share a directory, or with concurrent uploads of identical
  client file names. Integrators should give sources unique names (UUID/hash) before compressing.
- There is no option to choose the output name: rename `$r->path` afterwards if needed.

### 4.3 Path quirks
- `outputDirectory` is concatenated as `$dir . '/' . $name`. A trailing slash yields a double
  slash (`/var/www/out//photo_compressed.webp`) — harmless for the filesystem, but breaks string
  comparisons and URL building. Pass `rtrim($dir, '/')`.
- Paths are not normalised/realpath'd; a relative `sourcePath` gives a relative `path`.

### 4.4 Formats & transparency (verified empirically)

| Input | GD | Imagick |
|---|---|---|
| JPEG / PNG / WebP | yes | yes |
| GIF | first frame | **last frame** (see below) |
| BMP, TIFF, AVIF, HEIC | `Failed to create image resource` | yes, if ImageMagick delegates installed |

| Transparency case | GD | Imagick |
|---|---|---|
| PNG (alpha) → WebP | preserved | preserved |
| WebP (alpha) → WebP | **lost — becomes black** | preserved |
| GIF (transparent index) → WebP | **lost** (key colour shows) | **lost** (key colour shows) |
| any alpha → JPG | no flattening: transparent areas become black or the hidden RGB of the pixels | same: hidden RGB shows |

If transparency matters: use WebP output, prefer Imagick, and for JPG output flatten onto a
background yourself beforehand.

**Animated GIFs:** despite what README states, **neither backend preserves animation**.
Output always contains a single frame: GD keeps the first, Imagick keeps the *last* one
(it writes with `writeImage()`, not `writeImages()`). Skip GIFs or handle them separately if
animation matters.

### 4.5 Metadata & orientation
- Imagick calls `stripImage()` → all EXIF/ICC/XMP removed (including GPS).
- GD re-encodes pixels only, so EXIF is not carried into the output either.
- **Neither backend applies EXIF orientation.** Phone photos stored rotated with an
  `Orientation` tag will come out sideways. If you accept camera uploads, auto-orient first
  (e.g. `Imagick::autoOrient()` / `exif_read_data()` + `imagerotate()`) and save, then compress.

### 4.6 Quality
- Range `0..100`, default `85`. Passed to `imagewebp`/`imagejpeg` (GD) or
  `setImageCompressionQuality` (Imagick). Same number ≠ same file size across backends.
- Output may be **larger** than the input (already-optimised small images). If that matters,
  compare `compressedSize` with `originalSize` and keep the original.

### 4.7 Runtime characteristics
- Synchronous, CPU-bound, blocking. Large uploads can take seconds — in web requests prefer a
  queue/worker (Symfony Messenger, Laravel queues).
- GD decodes into PHP heap: roughly `width × height × 4–5` bytes ×2 (source + resized).
  A 6000×4000 photo needs ~200 MB+. Raise `memory_limit` for workers or use Imagick.
- Imagick reads **any** format ImageMagick supports (incl. SVG/PDF/PS via delegates).
  For untrusted uploads, validate MIME/extension (allow-list) *before* calling `compress()`
  and keep a restrictive ImageMagick `policy.xml`.
- No logging, no events, no global state; services are reusable and thread/worker safe
  except for the output-name collision noted in §4.2.

---

## 5. Framework wiring

### Symfony (`config/services.yaml`)

```yaml
services:
    PicDiet\Service\ImageCompressorInterface:
        factory: ['PicDiet\Service\ImageCompressorFactory', 'createBest']
    # or force a backend:
    # PicDiet\Service\ImageCompressorInterface:
    #     factory: ['PicDiet\Service\ImageCompressorFactory', 'factory']
    #     arguments: [!php/enum PicDiet\Enum\CompressionStrategy::GD]
```

### Laravel (`AppServiceProvider::register()`)

```php
$this->app->singleton(
    \PicDiet\Service\ImageCompressorInterface::class,
    fn () => \PicDiet\Service\ImageCompressorFactory::createBest(),
);
```

Laravel's `store()` returns a disk-relative path: pass
`Storage::disk('local')->path($relative)` (absolute) as `sourcePath`. PicDiet works only on
the local filesystem — for S3 & co. download to a temp file, compress, upload `$r->path`.

### Plain PHP

```php
$compressor = \PicDiet\Service\ImageCompressorFactory::createBest();
```

---

## 6. Recipes

### Upload pipeline (recommended shape)

```php
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array(mime_content_type($tmp), $allowed, true)) { /* reject */ }

$src = $uploadDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext; // unique => no collisions
move_uploaded_file($tmp, $src);

$r = $compressor->compress($src, ImageFormatEnum::WEBP, 1920, 1080, 82, rtrim($publicDir, '/'));
if ($r->success) {
    unlink($src);                         // optional: drop the original
    $publicPath = $r->path;
}
```

### Multiple renditions from one source

```php
foreach (['lg' => 1920, 'md' => 1024, 'sm' => 320] as $suffix => $size) {
    $dir = "$base/$suffix";               // separate dirs: same output name per rendition
    is_dir($dir) || mkdir($dir, 0775, true);
    $renditions[$suffix] = $compressor->compress($src, ImageFormatEnum::WEBP, $size, $size, 80, $dir);
}
```

### Keep the original when compression doesn't help

```php
if ($r->success && $r->compressedSize >= $r->originalSize) {
    unlink($r->path);
    // use $src instead
}
```

### WebP + JPEG fallback (`<picture>`)

Call `compress()` twice with `WEBP` and `JPG`; both land next to each other as
`name_compressed.webp` / `name_compressed.jpg`.

---

## 7. Testing code that uses PicDiet

- Type-hint `ImageCompressorInterface`, never a concrete service.
- Mock it with PHPUnit and return real DTOs from the public named constructors:

```php
$compressor = $this->createMock(ImageCompressorInterface::class);
$compressor->method('compress')->willReturn(
    CompressionResponse::success('/tmp/x_compressed.webp', 1000, 400, ImageFormatEnum::WEBP, 'x_compressed.webp', '/tmp'),
);
// failure path:
// CompressionResponse::failure('Invalid image file', 1000, ImageFormatEnum::WEBP)
```

- Integration tests need ext-gd; guard Imagick tests with
  `ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)` and `markTestSkipped()`.
- `CompressionResponse` cannot be built with `new` (private constructor).

---

## 8. Do / Don't checklist for agents

- DO resolve the service via the factory or the DI container bound to `ImageCompressorInterface`.
- DO wrap `compress()` for `\InvalidArgumentException` **and** check `$r->success`.
- DO make sure `outputDirectory` exists and is writable (`mkdir(..., true)`) before calling.
- DO strip trailing slashes from `outputDirectory`.
- DO use unique source file names to avoid silent overwrites.
- DO pass explicit `maxWidth`/`maxHeight` — the 1920×1080 default always applies.
- DO auto-orient camera photos before compressing.
- DON'T expect animated GIFs, transparent GIF/WebP (GD) or JPG transparency to survive.
- DON'T expect the original to be deleted, or the output name to be configurable.
- DON'T pass remote URLs, stream wrappers or binary strings — only local file paths.
- DON'T show `$r->error` to end users (may contain server paths).
- DON'T feed unvalidated uploads to the Imagick backend.
- DON'T depend on `Dimensions`, `ImageInfo`, `AbstractImageCompressorService` or the
  `MAX_WIDTH`/`MAX_HEIGHT`/`DEFAULT_QUALITY` constants (protected, internal).
