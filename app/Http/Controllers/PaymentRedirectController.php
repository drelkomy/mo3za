<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentRedirectController extends Controller
{
    public function handleRedirect(Request $request)
    {
        // تنفيذ إعادة التوجيه
        return redirect('/admin');
    }
}