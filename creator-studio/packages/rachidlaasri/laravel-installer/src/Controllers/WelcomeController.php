<?php

namespace RachidLaasri\LaravelInstaller\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WelcomeController extends Controller
{
    /**
     * Display the installer welcome page.
     *
     * @return Response
     */
    public function welcome()
    {
        return view('vendor.installer.welcome');
    }
}
