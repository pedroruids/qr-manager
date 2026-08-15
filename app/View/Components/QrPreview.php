<?php

namespace App\View\Components;

use App\Models\QrCode;
use App\Services\GeradorQrCode;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * O código renderizado. Componente de classe, e não anónimo, porque precisa do
 * `GeradorQrCode`: é ele que sabe que o conteúdo é o URL curto do slug.
 */
class QrPreview extends Component
{
    public function __construct(
        public QrCode $qrCode,
        private readonly GeradorQrCode $gerador,
    ) {}

    /**
     * SVG, sempre — é vectorial, e a pré-visualização tem de aguentar qualquer
     * largura sem perder definição.
     */
    public function svg(): string
    {
        return $this->gerador->svg($this->qrCode);
    }

    public function render(): View
    {
        return view('components.qr-preview');
    }
}
