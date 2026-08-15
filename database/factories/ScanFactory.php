<?php

namespace Database\Factories;

use App\Models\QrCode;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_code_id' => QrCode::factory(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    /**
     * Leitura num dia concreto — o gráfico de leituras por dia precisa de
     * distribuir leituras pelo calendário.
     */
    public function em(Carbon $momento): static
    {
        return $this->state(fn (array $atributos): array => ['created_at' => $momento]);
    }

    /**
     * Leitor que não enviou User-Agent.
     */
    public function semUserAgent(): static
    {
        return $this->state(fn (array $atributos): array => ['user_agent' => null]);
    }
}
