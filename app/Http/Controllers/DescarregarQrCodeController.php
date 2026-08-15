<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Services\GeradorQrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Entrega o ficheiro que vai para a gráfica.
 *
 * Rota própria, e não um método do componente Livewire, porque um download é um
 * pedido HTTP normal: o browser tem de o poder abrir num separador, guardar e
 * repetir.
 */
class DescarregarQrCodeController extends Controller
{
    public function __invoke(Request $request, QrCode $qrCode, string $formato, GeradorQrCode $gerador): Response
    {
        // O código é de quem o criou. A quem não é dono responde-se como se não
        // existisse, tal como no redirect público.
        abort_unless($qrCode->user_id === $request->user()?->id, 404);

        abort_unless(in_array($formato, ['png', 'svg'], true), 404);

        $dados = $request->validate([
            'tamanho' => [
                'sometimes',
                'integer',
                'min:'.GeradorQrCode::TAMANHO_PNG_MINIMO,
                'max:'.GeradorQrCode::TAMANHO_PNG_MAXIMO,
            ],
        ]);

        $tamanho = (int) ($dados['tamanho'] ?? GeradorQrCode::TAMANHO_PNG_OMISSAO);

        [$conteudo, $tipo] = $formato === 'png'
            ? [$gerador->png($qrCode, $tamanho), 'image/png']
            : [$gerador->svg($qrCode), 'image/svg+xml'];

        return response($conteudo)
            ->header('Content-Type', $tipo)
            ->header('Content-Disposition', 'attachment; filename="'.$this->nomeDoFicheiro($qrCode, $formato).'"');
    }

    /**
     * Nome legível por quem o recebe: o que o utilizador chamou ao código, mais
     * o slug, que é o que distingue dois códigos com o mesmo nome.
     */
    private function nomeDoFicheiro(QrCode $qrCode, string $formato): string
    {
        return str($qrCode->nome)->slug()->append('-', $qrCode->slug, '.', $formato)->value();
    }
}
