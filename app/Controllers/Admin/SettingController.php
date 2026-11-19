<?php

namespace App\Controllers\Admin;

use App\Helpers\ImageUploader;
use App\Models\ActivityLog;
use App\Models\Setting;

class SettingController extends AdminController
{
    private const BACK = '/admin/settings';

    private const TEXT_KEYS = [
        'store_name', 'store_tagline', 'store_email', 'store_whatsapp',
        'social_instagram', 'social_facebook', 'social_tiktok',
        'origin_name', 'origin_phone', 'origin_address',
    ];

    /** Diisi dari pilihan wilayah, bukan dari input teks. */
    private const AREA_KEYS = [
        'origin_area_id', 'origin_province', 'origin_city', 'origin_district', 'origin_postal_code',
    ];

    public function index()
    {
        $this->guard();

        $current = Setting::many(array_merge(self::TEXT_KEYS, self::AREA_KEYS, ['store_logo', 'store_favicon']));

        echo $this->page($current, []);
    }

    public function update()
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $old = Setting::many(array_merge(self::AREA_KEYS, ['store_logo', 'store_favicon']));

        $values = [];
        foreach (self::TEXT_KEYS as $key) {
            $values[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }

        $errors = $this->validate($values);

        // Wilayah asal: nilai lama dipertahankan, atau diganti dari pilihan yang dicari lewat server.
        $areaSave = [];
        $areaId = isset($_POST['area_id']) && is_string($_POST['area_id']) ? trim($_POST['area_id']) : '';

        if ($areaId === '') {
            $errors['area'] = 'Cari lalu pilih kecamatan dari daftar yang muncul.';
        } elseif ($areaId !== $old['origin_area_id']) {
            $choice = $_SESSION['area_choices'][$areaId] ?? null;

            if (!is_array($choice)) {
                $errors['area'] = 'Wilayah tidak valid. Cari ulang lalu pilih dari daftar.';
            } elseif (!preg_match('/^\d{5}$/', (string) ($choice['postal_code'] ?? '')) || (string) ($choice['district'] ?? '') === '') {
                $errors['area'] = 'Wilayah ini tidak punya kode pos lengkap. Pilih wilayah lain di sekitarnya.';
            } else {
                $areaSave = [
                    'origin_area_id' => (string) $choice['id'],
                    'origin_province' => mb_substr((string) $choice['province'], 0, 100),
                    'origin_city' => mb_substr((string) $choice['city'], 0, 100),
                    'origin_district' => mb_substr((string) $choice['district'], 0, 100),
                    'origin_postal_code' => (string) $choice['postal_code'],
                ];
            }
        }

        // Upload hanya diproses jika data lain sudah valid.
        $newFiles = [];
        if (!$errors) {
            foreach (['store_logo' => 'logo', 'store_favicon' => 'favicon'] as $key => $field) {
                $file = $this->uploadedFile($field);
                if ($file === null) {
                    continue;
                }
                $saved = ImageUploader::save($file, 'branding');
                if ($saved['ok']) {
                    $newFiles[$key] = $saved['path'];
                } else {
                    $errors[$field] = $saved['error'];
                }
            }
        }

        if ($errors) {
            foreach ($newFiles as $path) {
                ImageUploader::delete($path); // batalkan upload yang sudah masuk
            }
            echo $this->page(array_merge($old, $values), $errors);
            return;
        }

        Setting::setMany(array_merge($values, $areaSave, $newFiles));

        foreach ($newFiles as $key => $path) {
            if ($old[$key] !== '') {
                ImageUploader::delete($old[$key]);
            }
        }

        $note = 'Mengubah pengaturan toko' . ($areaSave ? ' (wilayah asal pengiriman diganti)' : '');
        ActivityLog::record((int) ($_SESSION['user']['id'] ?? 0), 'update', 'settings', null, $note);

        $this->flash('success', 'Pengaturan disimpan.');
        $this->redirect(self::BACK);
    }

    /**
     * Validasi dan normalisasi (nomor WhatsApp dan telepon dibersihkan di tempat).
     */
    private function validate(array &$v): array
    {
        $e = [];
        $len = fn(string $s): int => mb_strlen($s);

        if ($len($v['store_name']) < 2 || $len($v['store_name']) > 100) {
            $e['store_name'] = 'Nama toko harus 2-100 karakter.';
        }
        if ($len($v['store_tagline']) > 150) {
            $e['store_tagline'] = 'Slogan maksimal 150 karakter.';
        }
        if ($v['store_email'] !== '' && (!filter_var($v['store_email'], FILTER_VALIDATE_EMAIL) || $len($v['store_email']) > 100)) {
            $e['store_email'] = 'Format email tidak valid.';
        }

        if ($v['store_whatsapp'] !== '') {
            $digits = (string) preg_replace('/\D+/', '', $v['store_whatsapp']);
            if (str_starts_with($digits, '0')) {
                $digits = '62' . substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62' . $digits;
            }
            if (!preg_match('/^62\d{8,13}$/', $digits)) {
                $e['store_whatsapp'] = 'Nomor WhatsApp tidak valid. Contoh: 081234567890.';
            } else {
                $v['store_whatsapp'] = $digits;
            }
        }

        foreach (['social_instagram', 'social_facebook', 'social_tiktok'] as $key) {
            if ($v[$key] !== '' && (strlen($v[$key]) > 255 || !preg_match('#^https://[^\s]+$#i', $v[$key]))) {
                $e[$key] = 'Isi dengan link lengkap berawalan https://.';
            }
        }

        // Alamat asal pengiriman (dipakai Biteship)
        if ($len($v['origin_name']) < 2 || $len($v['origin_name']) > 100) {
            $e['origin_name'] = 'Nama pengirim harus 2-100 karakter.';
        }

        $phone = (string) preg_replace('/[\s().-]+/', '', $v['origin_phone']);
        if (!preg_match('/^\+?\d{8,15}$/', $phone)) {
            $e['origin_phone'] = 'Nomor telepon tidak valid.';
        } else {
            $v['origin_phone'] = $phone;
        }

        if ($len($v['origin_address']) < 5 || $len($v['origin_address']) > 500) {
            $e['origin_address'] = 'Alamat harus 5-500 karakter.';
        }

        return $e;
    }

    private function uploadedFile(string $field): ?array
    {
        $f = $_FILES[$field] ?? null;

        if (!is_array($f) || !isset($f['error']) || is_array($f['error']) || (int) $f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => (string) ($f['name'] ?? ''),
            'tmp_name' => (string) ($f['tmp_name'] ?? ''),
            'error' => (int) $f['error'],
            'size' => (int) ($f['size'] ?? 0),
        ];
    }

    private function page(array $values, array $errors): string
    {
        return $this->render('Pengaturan', 'admin.settings.index', ['values' => $values, 'errors' => $errors]);
    }
}