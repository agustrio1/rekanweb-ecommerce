<?php

namespace App\Controllers\Store;

use App\Middleware\Auth;
use App\Models\Address;
use App\Models\Customer;
use App\Services\Biteship\AreaService;

class AddressController extends StoreController
{
    public function index()
    {
        Auth::handle();

        echo $this->render('account.addresses', [
            'title' => 'Alamat pengiriman',
            'active' => 'addresses',
            'addresses' => Address::forUser($this->userId()),
            'canAdd' => Address::count($this->userId()) < Address::MAX_PER_USER,
        ]);
    }

    public function create()
    {
        Auth::handle();

        if (Address::count($this->userId()) >= Address::MAX_PER_USER) {
            $this->flash('error', 'Maksimal ' . Address::MAX_PER_USER . ' alamat tersimpan.');
            $this->redirect('/akun/alamat');
        }

        echo $this->renderForm('/akun/alamat/tambah', 'Tambah alamat');
    }

    public function store()
    {
        Auth::handle();

        if (!$this->csrfValid()) {
            $this->redirect('/akun/alamat');
        }

        if (Address::count($this->userId()) >= Address::MAX_PER_USER) {
            $this->flash('error', 'Maksimal ' . Address::MAX_PER_USER . ' alamat tersimpan.');
            $this->redirect('/akun/alamat');
        }

        [$errors, $data, $old] = $this->validated(null);

        if (!empty($errors)) {
            echo $this->renderForm('/akun/alamat/tambah', 'Tambah alamat', $errors, $old);
            return;
        }

        Address::create($this->userId(), $data);

        $this->flash('success', 'Alamat berhasil disimpan.');
        $this->redirect($this->afterSave());
    }

    public function edit($id)
    {
        Auth::handle();

        $address = Address::find((int) $id, $this->userId());
        if ($address === null) {
            $this->notFound();
            return;
        }

        $old = [
            'recipient_name' => $address['recipient_name'],
            'phone' => $address['phone'],
            'address_detail' => $address['address_detail'],
            'is_default' => (int) $address['is_default'] === 1,
            'area_id' => (string) $address['area_id'],
            'area_name' => (string) $address['area_name'],
        ];

        echo $this->renderForm('/akun/alamat/' . $address['id'] . '/ubah', 'Ubah alamat', [], $old);
    }

    public function update($id)
    {
        Auth::handle();

        $address = Address::find((int) $id, $this->userId());
        if ($address === null) {
            $this->notFound();
            return;
        }

        $action = '/akun/alamat/' . $address['id'] . '/ubah';

        if (!$this->csrfValid()) {
            $this->redirect($action);
        }

        [$errors, $data, $old] = $this->validated($address);

        if (!empty($errors)) {
            echo $this->renderForm($action, 'Ubah alamat', $errors, $old);
            return;
        }

        Address::update((int) $address['id'], $this->userId(), $data);

        $this->flash('success', 'Alamat berhasil diperbarui.');
        $this->redirect('/akun/alamat');
    }

    public function makeDefault($id)
    {
        Auth::handle();

        if ($this->csrfValid()) {
            $ok = Address::makeDefault((int) $id, $this->userId());
            $this->flash($ok ? 'success' : 'error', $ok ? 'Alamat utama diperbarui.' : 'Alamat tidak ditemukan.');
        }

        $this->redirect('/akun/alamat');
    }

    public function destroy($id)
    {
        Auth::handle();

        if ($this->csrfValid()) {
            $ok = Address::delete((int) $id, $this->userId());
            $this->flash($ok ? 'info' : 'error', $ok ? 'Alamat dihapus.' : 'Alamat tidak ditemukan.');
        }

        $this->redirect('/akun/alamat');
    }

    /**
     * Endpoint JSON untuk autocomplete wilayah.
     */
    public function searchArea()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
            return;
        }

        // Batas 30 pencarian per menit per sesi.
        $limit = $_SESSION['area_rl'] ?? ['t' => 0, 'n' => 0];
        if (time() - (int) $limit['t'] > 60) {
            $limit = ['t' => time(), 'n' => 0];
        }
        $limit['n']++;
        $_SESSION['area_rl'] = $limit;

        if ($limit['n'] > 30) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'message' => 'Terlalu banyak pencarian. Tunggu sebentar.']);
            return;
        }

        $q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 60) : '';

        // Lepas kunci session sebelum memanggil Biteship, supaya request lain
        // (misalnya pencarian berikutnya) tidak tertahan menunggu.
        session_write_close();

        $result = AreaService::search($q);

        if (!$result['ok']) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'message' => $result['message']]);
            return;
        }

        // Buka session lagi untuk menyimpan pilihan. Saat alamat disimpan,
        // data wilayah diambil dari server, bukan dari form.
        session_start();

        $choices = $_SESSION['area_choices'] ?? [];
        foreach ($result['areas'] as $area) {
            $choices[$area['id']] = $area;
        }
        $_SESSION['area_choices'] = array_slice($choices, -60, null, true);
        session_write_close();

        echo json_encode(['ok' => true, 'areas' => $result['areas']], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{0: array, 1: array, 2: array} [errors, data siap simpan, old input]
     */
    private function validated(?array $existing): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['recipient_name'] ?? '')));
        $rawPhone = (string) ($_POST['phone'] ?? '');
        $phone = Customer::normalizePhone($rawPhone);
        $detail = trim((string) ($_POST['address_detail'] ?? ''));
        $isDefault = ($_POST['is_default'] ?? '') === '1';
        $areaId = trim((string) ($_POST['area_id'] ?? ''));

        $area = null;
        if ($areaId !== '') {
            if ($existing !== null && (string) $existing['area_id'] === $areaId) {
                $area = [
                    'id' => $areaId,
                    'name' => (string) $existing['area_name'],
                    'province' => $existing['province'],
                    'city' => $existing['city'],
                    'district' => $existing['district'],
                    'postal_code' => $existing['postal_code'],
                ];
            } elseif (isset($_SESSION['area_choices'][$areaId]) && is_array($_SESSION['area_choices'][$areaId])) {
                $area = $_SESSION['area_choices'][$areaId];
            }
        }

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['recipient_name'] = 'Nama penerima harus 2 sampai 100 karakter.';
        }

        if ($phone === null) {
            $errors['phone'] = 'Nomor HP tidak valid. Contoh: 081234567890.';
        }

        if ($area === null) {
            $errors['area'] = 'Cari lalu pilih kecamatan dari daftar yang muncul.';
        } elseif ($area['postal_code'] === '' || $area['district'] === '') {
            $errors['area'] = 'Wilayah ini tidak punya data kode pos. Pilih wilayah lain di sekitarnya.';
        }

        if (mb_strlen($detail) < 10 || mb_strlen($detail) > 300) {
            $errors['address_detail'] = 'Alamat lengkap harus 10 sampai 300 karakter (jalan, nomor, RT/RW, patokan).';
        }

        $old = [
            'recipient_name' => $name,
            'phone' => $rawPhone,
            'address_detail' => $detail,
            'is_default' => $isDefault,
            'area_id' => $area['id'] ?? '',
            'area_name' => $area['name'] ?? '',
        ];

        $data = [];
        if (empty($errors)) {
            $data = [
                'recipient_name' => $name,
                'phone' => $phone,
                'area_id' => $area['id'],
                'area_name' => $area['name'],
                'province' => $area['province'],
                'city' => $area['city'],
                'district' => $area['district'],
                'postal_code' => $area['postal_code'],
                'address_detail' => $detail,
                'is_default' => $isDefault,
            ];
        }

        return [$errors, $data, $old];
    }

    private function renderForm(string $action, string $title, array $errors = [], array $old = []): string
    {
        if (!empty($errors)) {
            http_response_code(422);
        }

        return $this->render('account.address-form', [
            'title' => $title,
            'active' => 'addresses',
            'action' => $action,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * Setelah simpan, kembali ke checkout jika pelanggan datang dari sana.
     */
    private function afterSave(): string
    {
        return ($_POST['return'] ?? '') === 'checkout' ? '/checkout' : '/akun/alamat';
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo $this->render('store.not-found', [
            'title' => 'Alamat tidak ditemukan',
            'message' => 'Alamat yang kamu cari tidak ada.',
        ]);
    }

    private function userId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}