<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class KeyVerificationController extends Controller
{
    public function __invoke(): Response
    {
        $key = trim((string) config('pondol-indexnow.key', ''));

        abort_if($key === '', 404);

        return response($key, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
