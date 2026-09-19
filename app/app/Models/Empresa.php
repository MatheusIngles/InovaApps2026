<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sinais' => 'array', 'similares' => 'array', 'hist' => 'array', 'nps' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    /** Ativas por receita em risco; canceladas sempre por último. */
    public static function ordenar(Builder $q): Builder
    {
        return $q->orderByRaw("status = 'Cancelado'")->orderByDesc('exposicao');
    }

    public static function ativas(): Collection
    {
        return static::where('status', 'Ativo')->orderByDesc('exposicao')->get();
    }

    public function cancelada(): bool
    {
        return $this->status === 'Cancelado';
    }

    /** Rótulo exibido no lugar do nível para empresas canceladas. */
    public function rotulo(): string
    {
        return $this->cancelada() ? 'Cancelada' : $this->nivel;
    }

    public static function brl(float|int $v): string
    {
        return 'R$ '.number_format($v, 0, ',', '.');
    }
}
