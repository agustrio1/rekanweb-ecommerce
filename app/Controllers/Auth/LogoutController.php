<?php

namespace App\Controllers\Auth;

use App\Core\Request;

class LogoutController
{
    public function store()
    {
        try {
            Request::validateCsrf();
        } catch (\Exception $e) {
            redirect('/');
        }

        AuthController::signOut();

        redirect('/');
    }
}