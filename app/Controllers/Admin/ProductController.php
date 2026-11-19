<?php

namespace App\Controllers\Admin;

use App\Helpers\HtmlSanitizer;
use App\Helpers\ImageUploader;
use App\Models\ActivityLog;
use App\Models\AdminCategory;
use App\Models\AdminProduct;
use PDOException;

class ProductController extends AdminController
{
    private const BACK = '/admin/products';

    public function index()
    {
        $this->guard();

        $q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 50) : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = AdminProduct::paginate($q, $page);

        echo $this->render('Produk', 'admin.products.index', [
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'q' => $q,
        ]);
    }

    public function create()
    {
        $this->guard();

        echo $this->form(null, [
            'name' => '',
            'category_id' => '',
            'description' => '',
            'weight_gram' => '',
            'length_cm' => '',
            'width_cm' => '',
            'height_cm' => '',
            'is_active' => 1,
            'variants' => [
                ['id' => 0, 'name' => 'Standar', 'sku' => '', 'price' => '', 'discount' => '', 'stock' => '0'],
            ],
        ], []);
    }

    public function store()
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        [$values, $data, $errors] = $this->validated(0);

        if (!$errors) {
            try {
                $id = AdminProduct::create($data);
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['variants'] = ['SKU sudah dipakai variasi lain.'];
            }
        }

        if ($errors) {
            echo $this->form(null, $values, $errors);
            return;
        }

        ActivityLog::record($this->adminId(), 'create', 'product', $id, 'Menambah produk ' . $data['name']);

        $this->flash('success', 'Produk ditambahkan. Tambahkan foto agar tampil di toko.');
        $this->redirect(self::BACK . '/' . $id . '/edit');
    }

    public function edit($id)
    {
        $this->guard();

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        echo $this->form($product, $this->valuesFrom($product), []);
    }

    public function update($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        [$values, $data, $errors] = $this->validated($product['id']);

        if (!$errors) {
            try {
                AdminProduct::update($product['id'], $data);
            } catch (\RuntimeException $e) {
                $errors['variants'] = [$e->getMessage()];
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['variants'] = ['SKU sudah dipakai variasi lain.'];
            }
        }

        if ($errors) {
            echo $this->form($product, $values, $errors);
            return;
        }

        ActivityLog::record($this->adminId(), 'update', 'product', $product['id'], 'Mengubah produk ' . $data['name']);

        $this->flash('success', 'Produk berhasil diperbarui.');
        $this->redirect(self::BACK . '/' . $product['id'] . '/edit');
    }

    public function toggle($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        $newState = $product['is_active'] !== 1;
        AdminProduct::setActive($product['id'], $newState);
        ActivityLog::record(
            $this->adminId(),
            $newState ? 'activate' : 'deactivate',
            'product',
            $product['id'],
            ($newState ? 'Mengaktifkan' : 'Menonaktifkan') . ' produk ' . $product['name']
        );

        $this->flash('success', $newState ? 'Produk diaktifkan.' : 'Produk dinonaktifkan.');
        $this->redirect(self::BACK);
    }

    public function destroy($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        $paths = AdminProduct::delete($product['id']);
        if ($paths === null) {
            $this->flash('error', 'Produk sudah punya riwayat pesanan sehingga tidak bisa dihapus. Nonaktifkan saja.');
            $this->redirect(self::BACK);
            return;
        }

        foreach ($paths as $path) {
            ImageUploader::delete((string) $path);
        }

        ActivityLog::record($this->adminId(), 'delete', 'product', $product['id'], 'Menghapus produk ' . $product['name']);

        $this->flash('success', 'Produk dihapus.');
        $this->redirect(self::BACK);
    }

    // ---------------------------------------------------------------- Gambar

    public function uploadImages($id)
    {
        $this->guard();

        $back = self::BACK . '/' . (int) $id . '/edit';

        if (!$this->csrfValid()) {
            $this->redirect($back);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        $files = ImageUploader::normalize($_FILES['images'] ?? null);
        if (!$files) {
            $this->flash('error', 'Pilih minimal satu foto.');
            $this->redirect($back);
            return;
        }

        $slots = AdminProduct::MAX_IMAGES - count($product['images']);
        if ($slots <= 0) {
            $this->flash('error', 'Maksimal ' . AdminProduct::MAX_IMAGES . ' foto per produk. Hapus foto lama dulu.');
            $this->redirect($back);
            return;
        }

        $saved = 0;
        $problems = [];

        foreach (array_slice($files, 0, $slots) as $file) {
            $result = ImageUploader::save($file, 'products', true);
            if ($result['ok']) {
                AdminProduct::addImage($product['id'], $result['path']);
                $saved++;
            } else {
                $problems[] = $result['error'];
            }
        }

        if (count($files) > $slots) {
            $problems[] = 'Hanya ' . $slots . ' foto yang muat, sisanya dilewati.';
        }

        if ($saved > 0) {
            ActivityLog::record($this->adminId(), 'upload_image', 'product', $product['id'], 'Mengunggah ' . $saved . ' foto produk ' . $product['name']);
        }

        if ($saved > 0 && $problems) {
            $this->flash('info', $saved . ' foto diunggah. ' . $problems[0]);
        } elseif ($saved > 0) {
            $this->flash('success', $saved . ' foto diunggah.');
        } else {
            $this->flash('error', $problems[0] ?? 'Foto gagal diunggah.');
        }

        $this->redirect($back);
    }

    public function primaryImage($id)
    {
        $this->guard();

        $back = self::BACK . '/' . (int) $id . '/edit';

        if (!$this->csrfValid()) {
            $this->redirect($back);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        $imageId = (int) ($_POST['image_id'] ?? 0);
        if (AdminProduct::findImage($product['id'], $imageId) === null) {
            $this->flash('error', 'Foto tidak ditemukan.');
            $this->redirect($back);
            return;
        }

        AdminProduct::setPrimaryImage($product['id'], $imageId);
        ActivityLog::record($this->adminId(), 'primary_image', 'product', $product['id'], 'Mengganti foto utama produk ' . $product['name']);

        $this->flash('success', 'Foto utama diganti.');
        $this->redirect($back);
    }

    public function deleteImage($id)
    {
        $this->guard();

        $back = self::BACK . '/' . (int) $id . '/edit';

        if (!$this->csrfValid()) {
            $this->redirect($back);
            return;
        }

        $product = $this->findOrRedirect($id);
        if ($product === null) {
            return;
        }

        $path = AdminProduct::deleteImage($product['id'], (int) ($_POST['image_id'] ?? 0));
        if ($path === null) {
            $this->flash('error', 'Foto tidak ditemukan.');
            $this->redirect($back);
            return;
        }

        ImageUploader::delete($path);
        ActivityLog::record($this->adminId(), 'delete_image', 'product', $product['id'], 'Menghapus foto produk ' . $product['name']);

        $this->flash('success', 'Foto dihapus.');
        $this->redirect($back);
    }

    // --------------------------------------------------------------- Internal

    private function findOrRedirect($id): ?array
    {
        $product = AdminProduct::find((int) $id);

        if ($product === null) {
            $this->flash('error', 'Produk tidak ditemukan.');
            $this->redirect(self::BACK);
        }

        return $product;
    }

    private function valuesFrom(array $product): array
    {
        $variants = [];
        foreach ($product['variants'] as $v) {
            $variants[] = [
                'id' => $v['id'],
                'name' => $v['variant_name'],
                'sku' => $v['sku'],
                'price' => (string) $v['price'],
                'discount' => $v['discount_price'] !== null ? (string) $v['discount_price'] : '',
                'stock' => (string) $v['stock'],
            ];
        }

        return [
            'name' => $product['name'],
            'category_id' => (string) $product['category_id'],
            'description' => (string) $product['description'],
            'weight_gram' => (string) $product['weight_gram'],
            'length_cm' => (string) $product['length_cm'],
            'width_cm' => (string) $product['width_cm'],
            'height_cm' => (string) $product['height_cm'],
            'is_active' => $product['is_active'],
            'variants' => $variants,
        ];
    }

    /**
     * Validasi input form produk.
     *
     * @return array{0: array, 1: array, 2: array} [nilai untuk ditampilkan ulang, data bersih, error]
     */
    private function validated(int $productId): array
    {
        $errors = [];
        $text = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';

        $values = [
            'name' => $text('name'),
            'category_id' => $text('category_id'),
            // Deskripsi berasal dari rich editor: selalu dibersihkan di server.
            'description' => HtmlSanitizer::clean($text('description')),
            'weight_gram' => $text('weight_gram'),
            'length_cm' => $text('length_cm'),
            'width_cm' => $text('width_cm'),
            'height_cm' => $text('height_cm'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'variants' => [],
        ];

        $data = ['is_active' => $values['is_active'], 'variants' => []];

        // Nama
        $nameLength = mb_strlen($values['name']);
        if ($nameLength < 2 || $nameLength > 200) {
            $errors['name'] = 'Nama produk harus 2-200 karakter.';
        }
        $data['name'] = $values['name'];

        // Kategori
        if ($values['category_id'] === '' || !ctype_digit($values['category_id']) || AdminCategory::find((int) $values['category_id']) === null) {
            $errors['category_id'] = 'Pilih kategori.';
        }
        $data['category_id'] = (int) $values['category_id'];

        // Deskripsi (HTML bersih)
        if (strlen($values['description']) > 30000 || mb_strlen(trim(strip_tags($values['description']))) > 5000) {
            $errors['description'] = 'Deskripsi terlalu panjang (maksimal 5000 karakter teks).';
        }
        $data['description'] = $values['description'] !== '' ? $values['description'] : null;

        // Berat dan dimensi (dipakai untuk ongkir Biteship)
        $number = function (string $key, int $min, int $max, string $label) use (&$errors, &$data, $values): void {
            $raw = $values[$key];
            if ($raw === '' || !ctype_digit($raw) || strlen($raw) > 9 || (int) $raw < $min || (int) $raw > $max) {
                $errors[$key] = $label . ' harus angka ' . $min . '-' . $max . '.';

                return;
            }
            $data[$key] = (int) $raw;
        };
        $number('weight_gram', 1, 100000, 'Berat');
        $number('length_cm', 1, 500, 'Panjang');
        $number('width_cm', 1, 500, 'Lebar');
        $number('height_cm', 1, 500, 'Tinggi');

        // Variasi
        $rawRows = isset($_POST['variants']) && is_array($_POST['variants']) ? array_values($_POST['variants']) : [];
        $variantErrors = [];
        $seenSkus = [];

        foreach (array_slice($rawRows, 0, 50) as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $get = fn(string $key): string => isset($raw[$key]) && is_string($raw[$key]) ? trim($raw[$key]) : '';
            $n = count($values['variants']) + 1;
            $prefix = 'Variasi ' . $n . ': ';

            $row = [
                'id' => (int) $get('id'),
                'name' => $get('name'),
                'sku' => strtoupper($get('sku')),
                'price' => $get('price'),
                'discount' => $get('discount'),
                'stock' => $get('stock'),
            ];
            $values['variants'][] = $row;

            $ok = true;
            $isInt = fn(string $v, int $min, int $max): bool => $v !== '' && ctype_digit($v) && strlen($v) <= 9 && (int) $v >= $min && (int) $v <= $max;

            if ($row['name'] === '' || mb_strlen($row['name']) > 100) {
                $variantErrors[] = $prefix . 'nama harus 1-100 karakter.';
                $ok = false;
            }

            if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/', $row['sku'])) {
                $variantErrors[] = $prefix . 'SKU hanya boleh huruf, angka, titik, minus, dan garis bawah.';
                $ok = false;
            } elseif (isset($seenSkus[$row['sku']])) {
                $variantErrors[] = $prefix . 'SKU ' . $row['sku'] . ' dipakai dua kali di form ini.';
                $ok = false;
            } elseif (AdminProduct::skuTaken($row['sku'], $row['id'])) {
                $variantErrors[] = $prefix . 'SKU ' . $row['sku'] . ' sudah dipakai produk lain.';
                $ok = false;
            }
            $seenSkus[$row['sku']] = true;

            $priceOk = $isInt($row['price'], 1, 999999999);
            if (!$priceOk) {
                $variantErrors[] = $prefix . 'harga harus angka minimal 1.';
                $ok = false;
            }

            $discount = null;
            if ($row['discount'] !== '') {
                if (!$isInt($row['discount'], 1, 999999999) || ($priceOk && (int) $row['discount'] >= (int) $row['price'])) {
                    $variantErrors[] = $prefix . 'harga diskon harus lebih kecil dari harga normal.';
                    $ok = false;
                } else {
                    $discount = (int) $row['discount'];
                }
            }

            if (!$isInt($row['stock'], 0, 999999)) {
                $variantErrors[] = $prefix . 'stok harus angka 0 atau lebih.';
                $ok = false;
            }

            if ($ok) {
                $data['variants'][] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'price' => (int) $row['price'],
                    'discount' => $discount,
                    'stock' => (int) $row['stock'],
                ];
            }
        }

        if (!$values['variants']) {
            $variantErrors[] = 'Minimal satu variasi.';
            $values['variants'][] = ['id' => 0, 'name' => 'Standar', 'sku' => '', 'price' => '', 'discount' => '', 'stock' => '0'];
        }

        if ($variantErrors) {
            $errors['variants'] = $variantErrors;
        }

        return [$values, $data, $errors];
    }

    private function form(?array $product, array $values, array $errors): string
    {
        return $this->render(
            $product ? 'Ubah produk' : 'Tambah produk',
            'admin.products.form',
            [
                'product' => $product,
                'values' => $values,
                'errors' => $errors,
                'categories' => AdminCategory::all(),
            ]
        );
    }

    private function adminId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }
}