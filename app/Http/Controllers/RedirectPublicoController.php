<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RedirectPublicoController extends Controller
{
    /**
     * Comprimento da coluna `scans.user_agent`. O cabeçalho não tem limite
     * prático e chega do lado de fora: trunca-se, nunca se recusa a leitura.
     */
    private const MAX_USER_AGENT = 512;

    public function __invoke(Request $request, string $slug): RedirectResponse|Response
    {
        $qrCode = QrCode::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->first();

        // Slug que nunca existiu e slug desactivado dão a mesma resposta. Uma
        // página diferente para cada caso diria a um estranho que aquele
        // endereço já foi de alguém.
        if ($qrCode === null) {
            return response()->view('errors.codigo-inactivo', status: 404);
        }

        $qrCode->scans()->create([
            'user_agent' => $this->userAgent($request),
        ]);

        return redirect()->away($qrCode->destino, 302);
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, self::MAX_USER_AGENT);
    }
}
