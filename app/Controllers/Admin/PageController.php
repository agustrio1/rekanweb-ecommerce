<?php

namespace App\Controllers\Admin;

use App\Helpers\HtmlSanitizer;
use App\Models\ActivityLog;
use App\Models\AdminPage;

class PageController extends AdminController
{
    private const BACK = '/admin/pages';

    public function index()
    {
        $this->guard();

        echo $this->render('Halaman', 'admin.pages.index', [
            'pages' => AdminPage::all(),
        ]);
    }

    public function edit($slug)
    {
        $this->guard();

        $page = $this->findOrRedirect($slug);
        if ($page === null) {
            return;
        }

        echo $this->form($page, [
            'title' => $page['title'],
            'content' => (string) $page['content'],
            'is_active' => $page['is_active'],
        ], []);
    }

    public function update($slug)
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $page = $this->findOrRedirect($slug);
        if ($page === null) {
            return;
        }

        $title = isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : '';
        $raw = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : '';

        $values = [
            'title' => $title,
            'content' => HtmlSanitizer::clean($raw),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = [];

        if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
            $errors['title'] = 'Judul harus 2-150 karakter.';
        }

        if (strlen($values['content']) > 100000 || mb_strlen(trim(strip_tags($values['content']))) > 20000) {
            $errors['content'] = 'Isi halaman terlalu panjang (maksimal 20000 karakter teks).';
        }

        if ($errors) {
            echo $this->form($page, $values, $errors);
            return;
        }

        AdminPage::update($page['id'], $values);
        ActivityLog::record($this->adminId(), 'update', 'page', $page['id'], 'Mengubah halaman ' . $values['title']);

        $this->flash('success', 'Halaman diperbarui.');
        $this->redirect(self::BACK);
    }

    private function findOrRedirect($slug): ?array
    {
        $page = AdminPage::find((string) $slug);

        if ($page === null) {
            $this->flash('error', 'Halaman tidak ditemukan.');
            $this->redirect(self::BACK);
        }

        return $page;
    }

    private function form(array $page, array $values, array $errors): string
    {
        return $this->render('Ubah halaman', 'admin.pages.form', [
            'page' => $page,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    private function adminId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }
}