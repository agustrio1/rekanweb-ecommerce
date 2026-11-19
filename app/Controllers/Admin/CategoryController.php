<?php

namespace App\Controllers\Admin;

use App\Models\ActivityLog;
use App\Models\AdminCategory;

class CategoryController extends AdminController
{
    private const BACK = '/admin/categories';

    public function index()
    {
        $this->guard();

        echo $this->render('Kategori', 'admin.categories.index', [
            'categories' => AdminCategory::all(),
        ]);
    }

    public function create()
    {
        $this->guard();

        echo $this->form(null, ['name' => '', 'description' => '', 'is_active' => 1, 'is_featured' => 0], []);
    }

    public function store()
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        [$values, $errors] = $this->validated(0);

        if ($errors) {
            echo $this->form(null, $values, $errors);
            return;
        }

        $id = AdminCategory::create($values);
        ActivityLog::record($this->adminId(), 'create', 'category', $id, 'Menambah kategori ' . $values['name']);

        $this->flash('success', 'Kategori berhasil ditambahkan.');
        $this->redirect(self::BACK);
    }

    public function edit($id)
    {
        $this->guard();

        $category = AdminCategory::find((int) $id);
        if ($category === null) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            $this->redirect(self::BACK);
            return;
        }

        echo $this->form($category, [
            'name' => $category['name'],
            'description' => (string) $category['description'],
            'is_active' => $category['is_active'],
            'is_featured' => $category['is_featured'],
        ], []);
    }

    public function update($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $category = AdminCategory::find((int) $id);
        if ($category === null) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            $this->redirect(self::BACK);
            return;
        }

        [$values, $errors] = $this->validated($category['id']);

        if ($errors) {
            echo $this->form($category, $values, $errors);
            return;
        }

        AdminCategory::update($category['id'], $values);
        ActivityLog::record($this->adminId(), 'update', 'category', $category['id'], 'Mengubah kategori ' . $values['name']);

        $this->flash('success', 'Kategori berhasil diperbarui.');
        $this->redirect(self::BACK);
    }

    public function toggle($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $category = AdminCategory::find((int) $id);
        if ($category === null) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            $this->redirect(self::BACK);
            return;
        }

        $newState = $category['is_active'] !== 1;
        AdminCategory::setActive($category['id'], $newState);
        ActivityLog::record(
            $this->adminId(),
            $newState ? 'activate' : 'deactivate',
            'category',
            $category['id'],
            ($newState ? 'Mengaktifkan' : 'Menonaktifkan') . ' kategori ' . $category['name']
        );

        $this->flash('success', $newState ? 'Kategori diaktifkan.' : 'Kategori dinonaktifkan.');
        $this->redirect(self::BACK);
    }

    public function feature($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $category = AdminCategory::find((int) $id);
        if ($category === null) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            $this->redirect(self::BACK);
            return;
        }

        $newState = $category['is_featured'] !== 1;
        AdminCategory::setFeatured($category['id'], $newState);
        ActivityLog::record(
            $this->adminId(),
            $newState ? 'feature' : 'unfeature',
            'category',
            $category['id'],
            ($newState ? 'Menjadikan unggulan' : 'Melepas unggulan') . ' kategori ' . $category['name']
        );

        $this->flash('success', $newState ? 'Kategori dijadikan unggulan.' : 'Kategori tidak lagi unggulan.');
        $this->redirect(self::BACK);
    }

    public function destroy($id)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $category = AdminCategory::find((int) $id);
        if ($category === null) {
            $this->flash('error', 'Kategori tidak ditemukan.');
            $this->redirect(self::BACK);
            return;
        }

        if (!AdminCategory::delete($category['id'])) {
            $this->flash('error', 'Kategori masih memiliki produk. Nonaktifkan saja, atau pindahkan produknya dulu.');
            $this->redirect(self::BACK);
            return;
        }

        ActivityLog::record($this->adminId(), 'delete', 'category', $category['id'], 'Menghapus kategori ' . $category['name']);

        $this->flash('success', 'Kategori dihapus.');
        $this->redirect(self::BACK);
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function validated(int $ignoreId): array
    {
        $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';

        $values = [
            'name' => $name,
            'description' => $description,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Nama kategori harus 2-100 karakter.';
        } elseif (AdminCategory::nameExists($name, $ignoreId)) {
            $errors['name'] = 'Nama kategori sudah dipakai.';
        }

        if (mb_strlen($description) > 500) {
            $errors['description'] = 'Deskripsi maksimal 500 karakter.';
        }

        return [$values, $errors];
    }

    private function form(?array $category, array $values, array $errors): string
    {
        return $this->render(
            $category ? 'Ubah kategori' : 'Tambah kategori',
            'admin.categories.form',
            ['category' => $category, 'values' => $values, 'errors' => $errors]
        );
    }

    private function adminId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }
}