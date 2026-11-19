<?php

namespace App\Helpers;

class ImageUploader
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_PIXELS = 16000000; // batas memori saat decode (GD)
    private const MAX_SIDE = 1600;
    private const THUMB_SIDE = 480;
    private const PATH_RE = '#^/uploads/[a-z0-9_-]+/[A-Za-z0-9][A-Za-z0-9._-]*$#';

    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Ubah struktur $_FILES['x'] (multiple) menjadi daftar file.
     * Input kosong (tidak ada file dipilih) dilewati.
     */
    public static function normalize($files): array
    {
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $out = [];
        foreach ($files['name'] as $i => $name) {
            $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $out[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                'error' => $error,
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Simpan satu gambar ke {DOCUMENT_ROOT}/uploads/{folder}/.
     * Jika GD dengan WebP tersedia, gambar dikonversi ke WebP (maks 1600 px).
     * Dengan $thumb = true dibuat juga versi kecil (NAMA-sm.webp) untuk kartu produk.
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public static function save(array $file, string $folder, bool $thumb = false): array
    {
        $label = mb_substr($file['name'], 0, 40);

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return self::fail($label . ': ukuran melebihi batas server.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return self::fail($label . ': gagal diunggah.');
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            return self::fail($label . ': ukuran maksimal 2 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return self::fail($label . ': file tidak valid.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) {
            return self::fail($label . ': format harus JPG, PNG, atau WebP.');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return self::fail($label . ': bukan gambar yang valid.');
        }
        if ((int) $info[0] * (int) $info[1] > self::MAX_PIXELS) {
            return self::fail($label . ': resolusi terlalu besar (maksimal 16 megapiksel).');
        }

        $root = self::root();
        if ($root === '') {
            return self::fail('Folder web tidak terdeteksi.');
        }

        $dir = $root . '/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::fail('Folder upload tidak bisa dibuat. Periksa izin folder.');
        }

        $base = bin2hex(random_bytes(16));

        if (self::canOptimize()) {
            $img = self::load($file['tmp_name'], $mime);
            if ($img === null) {
                return self::fail($label . ': gambar tidak bisa diproses.');
            }
            if ($mime === 'image/jpeg') {
                $img = self::orient($img, $file['tmp_name']);
            }

            $main = self::fit($img, self::MAX_SIDE);
            if (!@imagewebp($main, $dir . '/' . $base . '.webp', 82)) {
                return self::fail($label . ': gagal disimpan.');
            }

            if ($thumb) {
                $small = self::fit($img, self::THUMB_SIDE);
                @imagewebp($small, $dir . '/' . $base . '-sm.webp', 78); // gagal tidak fatal
            }

            return ['ok' => true, 'path' => '/uploads/' . $folder . '/' . $base . '.webp'];
        }

        // Tanpa GD/WebP: simpan file asli (nama tetap diacak).
        $name = $base . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return self::fail($label . ': gagal disimpan.');
        }

        return ['ok' => true, 'path' => '/uploads/' . $folder . '/' . $name];
    }

    /**
     * Path thumbnail jika ada, selain itu path aslinya (data lama tetap tampil).
     */
    public static function thumb(string $path): string
    {
        if (!preg_match(self::PATH_RE, $path) || !str_ends_with($path, '.webp') || str_ends_with($path, '-sm.webp')) {
            return $path;
        }

        $small = substr($path, 0, -5) . '-sm.webp';

        return is_file(self::root() . $small) ? $small : $path;
    }

    /**
     * Hapus file hasil upload beserta thumbnail-nya.
     * Hanya path di bawah /uploads/{folder}/ yang diproses.
     */
    public static function delete(string $path): void
    {
        if (str_contains($path, '..') || !preg_match(self::PATH_RE, $path)) {
            return;
        }

        $root = self::root();
        if ($root === '') {
            return;
        }

        $files = [$root . $path];
        if (str_ends_with($path, '.webp') && !str_ends_with($path, '-sm.webp')) {
            $files[] = $root . substr($path, 0, -5) . '-sm.webp';
        }

        foreach ($files as $full) {
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    private static function canOptimize(): bool
    {
        return function_exists('imagewebp')
            && function_exists('imagecreatefromjpeg')
            && function_exists('imagecreatefrompng')
            && function_exists('imagecreatefromwebp');
    }

    private static function load(string $path, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (!$img) {
            return null;
        }

        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);

        return $img;
    }

    /**
     * Terapkan orientasi EXIF (foto HP), karena metadata hilang setelah encode ulang.
     */
    private static function orient(\GdImage $img, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $img;
        }

        $rotated = imagerotate($img, $angle, 0);

        return $rotated ?: $img;
    }

    /**
     * Perkecil agar sisi terpanjang tidak melebihi $max. Gambar kecil dikembalikan apa adanya.
     */
    private static function fit(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);

        if ($w <= $max && $h <= $max) {
            return $img;
        }

        $ratio = $max / max($w, $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private static function root(): string
    {
        return rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}