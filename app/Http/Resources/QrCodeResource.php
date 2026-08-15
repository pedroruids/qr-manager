<?php

namespace App\Http\Resources;

use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QrCode
 */
class QrCodeResource extends JsonResource
{
    /**
     * O que a API devolve por cada código.
     *
     * Traz o URL curto e os endereços dos ficheiros já montados: quem chama a
     * API está a automatizar, e não deve ter de saber compor endereços nossos
     * a partir do slug.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'nome' => $this->nome,
            'destino' => $this->destino,
            'activo' => $this->activo,
            'url_curto' => route('redirect.publico', $this->slug),
            'ficheiros' => [
                'png' => route('api.qr-codes.ficheiro', ['qrCode' => $this->slug, 'formato' => 'png']),
                'svg' => route('api.qr-codes.ficheiro', ['qrCode' => $this->slug, 'formato' => 'svg']),
            ],
            'leituras' => $this->whenCounted('scans'),
            'criado_em' => $this->created_at?->toIso8601String(),
            'actualizado_em' => $this->updated_at?->toIso8601String(),
        ];
    }
}
