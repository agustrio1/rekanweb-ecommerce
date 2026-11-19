<?php

namespace App\Controllers\Admin;

use App\Helpers\ImageUploader;
use App\Models\ActivityLog;
use App\Models\AdminBanner;

class BannerController extends AdminController
{
    private const BACK = '/admin/banners';

    public function index()
    {
        $this->guard();

        echo $this->render('Banner', 'admin.banners.index', [
            'banners' => AdminBanner::all(),
        ]);
    }

    public function create()
    {
        $this->guard();

        echo $this->form(null, ['title' => '', 'subtitle' => '', 'link_url' => '', 'sort_order' => '0', 'is_active' => 1], []);
    }

    public function store()
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        [$values, $errors] = $this->validated();

        $file = $this->uploadedFile();
        if ($file === null) {
            $errors['image'] = 'Pilih gambar banner.';
        }

        if (!$errors) {
            $saved = ImageUploader::save($file, 'banners');
            if ($saved['ok']) {
                $values['image_path'] = $saved['path'];
            } else {
                $errors['image'] = $saved['error'];
            }
        }

        if ($errors) {
            echo $this->form(null, $values, $errors);
            return;
        }

        $id = AdminBanner::create($values);
        ActivityLog::record($this->adminId(), 'create', 'banner', $id, 'Menambah banner ' . $values['title']);

        $this->flash('success', 'Banner ditambahkan.');
        $this->redirect(self::BACK);
    }

    public function edit($id)
    {
        $this->guard();

        $banner = $this->findOrRedirect($id);
        if ($banner === null) {
            return;
        }

        echo $this->form($banner, [
            'title' => $banner['title'],
            'subtitle' => (string) $banner['subtitle'],
            'link_url' => (string) $banner['link_url'],
            'sort_order' => (string) $banner['sort_order'],
            'is_active' => $banner['is_active'],
        ], []);
    }

    public function update($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $banner = $this->findOrRedirect($id);
        if ($banner === null) {
            return;
        }

        [$values, $errors] = $this->validated();
        $values['image_path'] = $banner['image_path'];
        $newImage = false;

        $file = $this->uploadedFile();
        if (!$errors && $file !== null) {
            $saved = ImageUploader::save($file, 'banners');
            if ($saved['ok']) {
                $values['image_path'] = $saved['path'];
                $newImage = true;
            } else {
                $errors['image'] = $saved['error'];
            }
        }

        if ($errors) {
            echo $this->form($banner, $values, $errors);
            return;
        }

        AdminBanner::update($banner['id'], $values);

        if ($newImage) {
            ImageUploader::delete($banner['image_path']);
        }

        ActivityLog::record($this->adminId(), 'update', 'banner', $banner['id'], 'Mengubah banner ' . $values['title']);

        $this->flash('success', 'Banner diperbarui.');
        $this->redirect(self::BACK);
    }

    public function toggle($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $banner = $this->findOrRedirect($id);
        if ($banner === null) {
            return;
        }

        $newState = $banner['is_active'] !== 1;
        AdminBanner::setActive($banner['id'], $newState);
        ActivityLog::record(
            $this->adminId(),
            $newState ? 'activate' : 'deactivate',
            'banner',
            $banner['id'],
            ($newState ? 'Mengaktifkan' : 'Menonaktifkan') . ' banner ' . $banner['title']
        );

        $this->flash('success', $newState ? 'Banner diaktifkan.' : 'Banner dinonaktifkan.');
        $this->redirect(self::BACK);
    }

    public function destroy($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $banner = $this->findOrRedirect($id);
        if ($banner === null) {
            return;
        }

        AdminBanner::delete($banner['id']);
        ImageUploader::delete($banner['image_path']);
        ActivityLog::record($this->adminId(), 'delete', 'banner', $banner['id'], 'Menghapus banner ' . $banner['title']);

        $this->flash('success', 'Banner dihapus.');
        $this->redirect(self::BACK);
    }

    private function findOrRedirect($id): ?array
    {
        $banner = AdminBanner::find((int) $id);

        if ($banner === null) {
            $this->flash('error', 'Banner tidak ditemukan.');
            $this->redirect(self::BACK);
        }

        return $banner;
    }

    /**
     * File dari input tunggal name="image". Null jika tidak ada file dipilih.
     */
    private function uploadedFile(): ?array
    {
        $f = $_FILES['image'] ?? null;

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

    /**
     * @return array{0: array, 1: array}
     */
    private function validated(): array
    {
        $text = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';

        $values = [
            'title' => $text('title'),
            'subtitle' => $text('subtitle'),
            'link_url' => $text('link_url'),
            'sort_order' => $text('sort_order'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = [];

        if (mb_strlen($values['title']) < 2 || mb_strlen($values['title']) > 150) {
            $errors['title'] = 'Judul harus 2-150 karakter.';
        }

        if (mb_strlen($values['subtitle']) > 255) {
            $errors['subtitle'] = 'Teks pendukung maksimal 255 karakter.';
        }

        $link = $values['link_url'];
        if ($link !== '') {
            $internal = $link[0] === '/' && !str_starts_with($link, '//');
            $external = (bool) preg_match('#^https://[^\s]+$#i', $link);
            if (strlen($link) > 255 || !($internal || $external)) {
                $errors['link_url'] = 'Link harus diawali "/" (halaman toko) atau "https://".';
            }
        }

        if (!preg_match('/^-?\d{1,6}$/', $values['sort_order'])) {
            $errors['sort_order'] = 'Urutan harus angka bulat.';
        }
        $values['sort_order'] = (int) $values['sort_order'];

        return [$values, $errors];
    }

    private function form(?array $banner, array $values, array $errors): string
    {
        return $this->render(
            $banner ? 'Ubah banner' : 'Tambah banner',
            'admin.banners.form',
            ['banner' => $banner, 'values' => $values, 'errors' => $errors]
        );
    }

    private function adminId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }
}