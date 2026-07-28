<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $session = $request->attributes->get('auth_session');

        if ($session instanceof AuthSession) {
            $session->update(['revoked_at' => now()]);
        }

        return response()->noContent();
    }
}
