<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QrCodeResource;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A API dos códigos QR.
 *
 * Tudo aqui parte da relação do utilizador do token — `$request->user()
 * ->qrCodes()` — e nunca do modelo. Um filtro esquecido numa consulta que
 * partisse de `QrCode::query()` devolvia códigos de outra pessoa; assim não há
 * caminho em que isso aconteça.
 */
class QrCodeController extends Controller
{
    private const POR_PAGINA = 25;

    /**
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, QrCode>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $codigos = $request->user()
            ->qrCodes()
            ->withCount('scans')
            ->latest()
            ->paginate(self::POR_PAGINA);

        return QrCodeResource::collection($codigos);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = validator(
            QrCode::normalizar($request->all()),
            QrCode::regras(),
            QrCode::mensagens()
        )->validate();

        $qrCode = $request->user()->qrCodes()->create($dados);

        return QrCodeResource::make($qrCode)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $slug): QrCodeResource
    {
        return QrCodeResource::make($this->doDono($request, $slug, comContagem: true));
    }

    public function update(Request $request, string $slug): QrCodeResource
    {
        $qrCode = $this->doDono($request, $slug);

        $dados = validator(QrCode::normalizar($request->all()), [
            // O slug é o que está impresso no papel. Recusa-se em vez de se
            // ignorar em silêncio: quem o tentou mudar tem de saber que não
            // mudou, senão fica a acreditar que mudou.
            'slug' => ['prohibited'],
            'nome' => ['sometimes', ...QrCode::regras()['nome']],
            'destino' => ['sometimes', ...QrCode::regras()['destino']],
            'activo' => QrCode::regras()['activo'],
        ], array_merge(QrCode::mensagens(), [
            'slug.prohibited' => 'O endereço curto de um código nunca muda: há material impresso a apontar para ele.',
        ]))->validate();

        $qrCode->update($dados);

        return QrCodeResource::make($qrCode);
    }

    /**
     * Um código que não é do dono do token responde como se não existisse. Um
     * 403 confirmaria que existe e é de outra pessoa.
     */
    private function doDono(Request $request, string $slug, bool $comContagem = false): QrCode
    {
        $consulta = $request->user()->qrCodes()->where('slug', $slug);

        if ($comContagem) {
            $consulta->withCount('scans');
        }

        $qrCode = $consulta->first();

        abort_if($qrCode === null, 404);

        return $qrCode;
    }
}
