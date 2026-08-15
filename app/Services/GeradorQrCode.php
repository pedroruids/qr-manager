<?php

namespace App\Services;

use App\Models\QrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;
use RuntimeException;

/**
 * Renderiza o código de um QR nos dois formatos que vão para a gráfica.
 *
 * O que fica codificado é sempre o URL curto do slug, nunca o destino: é essa
 * indirecção que permite mudar o destino sem reimprimir o papel.
 */
class GeradorQrCode
{
    /**
     * Módulos de margem à volta do código — a *quiet zone* que a norma exige.
     * Sem ela, um leitor não distingue onde o código acaba e o papel começa.
     */
    public const MARGEM_MODULOS = 4;

    public const TAMANHO_PNG_MINIMO = 128;

    /**
     * Tecto conservador: uma imagem em cor verdadeira ocupa 4 bytes por pixel
     * durante a geração, e 4096² passavam os 128 MB de `memory_limit` de uma
     * instalação por omissão. A 300 dpi, 2048 pixéis dão um código de 17 cm de
     * lado — muito acima do que um flyer leva.
     */
    public const TAMANHO_PNG_MAXIMO = 2048;

    public const TAMANHO_PNG_OMISSAO = 512;

    /**
     * O URL que fica dentro do código. Nunca o destino.
     */
    public function url(QrCode $qrCode): string
    {
        return route('redirect.publico', $qrCode->slug);
    }

    /**
     * SVG vectorial: caminhos e rectângulos, sem um único pixel embutido. É o
     * que a gráfica quer, porque amplia para um cartaz sem perder nada.
     */
    public function svg(QrCode $qrCode, int $tamanho = self::TAMANHO_PNG_OMISSAO): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($tamanho, self::MARGEM_MODULOS),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString(
            $this->url($qrCode),
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            $this->nivelDeCorreccao()
        );
    }

    /**
     * PNG desenhado módulo a módulo em GD, exactamente no tamanho pedido.
     *
     * Cada módulo ocupa um número inteiro de pixéis, e o que sobra vai para a
     * margem: meio pixel de módulo é uma aresta esbatida, e é aí que um leitor
     * barato, com pouca luz, deixa de ler o código impresso. A margem cresce,
     * nunca encolhe abaixo dos quatro módulos da norma.
     */
    public function png(QrCode $qrCode, int $tamanho = self::TAMANHO_PNG_OMISSAO): string
    {
        if ($tamanho < self::TAMANHO_PNG_MINIMO || $tamanho > self::TAMANHO_PNG_MAXIMO) {
            throw new InvalidArgumentException(sprintf(
                'O tamanho do PNG tem de estar entre %d e %d pixéis. Recebido: [%d]',
                self::TAMANHO_PNG_MINIMO,
                self::TAMANHO_PNG_MAXIMO,
                $tamanho
            ));
        }

        $matriz = Encoder::encode(
            $this->url($qrCode),
            $this->nivelDeCorreccao(),
            Encoder::DEFAULT_BYTE_MODE_ENCODING
        )->getMatrix();

        $modulos = $matriz->getWidth() + 2 * self::MARGEM_MODULOS;
        $escala = intdiv($tamanho, $modulos);

        if ($escala < 1) {
            throw new InvalidArgumentException(sprintf(
                'Um código com %d módulos e a margem da norma não cabe em %d pixéis.',
                $matriz->getWidth(),
                $tamanho
            ));
        }

        // O que sobra da divisão inteira soma-se à margem, repartido pelos dois
        // lados, em vez de esticar os módulos.
        $desenho = $matriz->getWidth() * $escala;
        $margem = intdiv($tamanho - $desenho, 2);

        $imagem = imagecreatetruecolor($tamanho, $tamanho);

        if ($imagem === false) {
            throw new RuntimeException('Não foi possível criar a imagem do código QR.');
        }

        try {
            $branco = imagecolorallocate($imagem, 255, 255, 255);
            $preto = imagecolorallocate($imagem, 0, 0, 0);

            if ($branco === false || $preto === false) {
                throw new RuntimeException('Não foi possível alocar as cores do código QR.');
            }

            // O fundo branco é a quiet zone e mantém-se nos dois temas da
            // aplicação: um código sobre fundo escuro pode não ser lido.
            imagefilledrectangle($imagem, 0, 0, $tamanho - 1, $tamanho - 1, $branco);

            for ($y = 0; $y < $matriz->getHeight(); $y++) {
                for ($x = 0; $x < $matriz->getWidth(); $x++) {
                    if ($matriz->get($x, $y) !== 1) {
                        continue;
                    }

                    $esquerda = $margem + $x * $escala;
                    $topo = $margem + $y * $escala;

                    imagefilledrectangle(
                        $imagem,
                        $esquerda,
                        $topo,
                        $esquerda + $escala - 1,
                        $topo + $escala - 1,
                        $preto
                    );
                }
            }

            ob_start();
            imagepng($imagem);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($imagem);
        }
    }

    /**
     * Nível Q, ~25% de tolerância a dano. Ver `docs/DECISIONS.md`.
     */
    private function nivelDeCorreccao(): ErrorCorrectionLevel
    {
        return ErrorCorrectionLevel::Q();
    }
}
