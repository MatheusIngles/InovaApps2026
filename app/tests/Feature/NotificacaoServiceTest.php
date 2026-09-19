<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\CustomerNps;
use App\Models\User;
use App\Support\RiskService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function empresaComUsuario(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $cliente = Customer::factory()->for($company)->create(['monthly_value' => 10000]);

        return [$company, $user, $cliente];
    }

    private function mes(Customer $cliente, string $mes, array $metricas, int $notaNps): void
    {
        CustomerMetric::factory()->for($cliente)->create(['reference_month' => $mes] + $metricas);
        CustomerNps::factory()->for($cliente)->create(['reference_month' => $mes, 'score' => $notaNps]);
    }

    public function test_notifica_quando_o_risco_sobe_de_nivel_entre_dois_meses_reais(): void
    {
        [$company, $user, $cliente] = $this->empresaComUsuario();

        $this->mes($cliente, '2026-01-01', ['platform_usage_percentage' => 90, 'sla_percentage' => 95, 'meetings_expected' => 1, 'meetings_completed' => 1], 9);
        RiskService::recalcular($company);
        $this->assertSame(0, $user->notifications()->count());

        $this->mes($cliente, '2026-02-01', ['platform_usage_percentage' => 5, 'sla_percentage' => 10, 'formal_complaints' => 4, 'payment_delay_days' => 20, 'meetings_expected' => 2, 'meetings_completed' => 2], 1);
        RiskService::recalcular($company);

        $notificacao = $user->notifications()->first();
        $this->assertNotNull($notificacao);
        $this->assertStringContainsString('subindo de nível', $notificacao->data['title']);
        $this->assertSame('danger', $notificacao->data['color']); // virou Crítico
    }

    public function test_notifica_baixo_contato_quando_ha_poucas_reunioes_realizadas(): void
    {
        [$company, $user, $cliente] = $this->empresaComUsuario();

        $this->mes($cliente, '2026-01-01', ['meetings_expected' => 2, 'meetings_completed' => 0], 8);
        RiskService::recalcular($company);

        $notificacao = $user->notifications()->first();
        $this->assertNotNull($notificacao);
        $this->assertStringContainsString('reaproximar', $notificacao->data['title']);
    }

    public function test_nao_duplica_notificacao_ao_recalcular_o_mesmo_mes_varias_vezes(): void
    {
        [$company, $user, $cliente] = $this->empresaComUsuario();

        $this->mes($cliente, '2026-01-01', ['platform_usage_percentage' => 90, 'sla_percentage' => 95, 'meetings_expected' => 1, 'meetings_completed' => 1], 9);
        RiskService::recalcular($company);
        $this->mes($cliente, '2026-02-01', ['platform_usage_percentage' => 20, 'sla_percentage' => 30, 'meetings_expected' => 2, 'meetings_completed' => 0], 2);
        RiskService::recalcular($company);
        $antes = $user->notifications()->count();

        RiskService::recalcular($company);
        RiskService::recalcular($company);

        $this->assertSame($antes, $user->notifications()->count());
    }

    public function test_mudanca_de_peso_no_mesmo_mes_nao_dispara_notificacao_de_escalonamento(): void
    {
        [$company, $user, $cliente] = $this->empresaComUsuario();

        $this->mes($cliente, '2026-01-01', ['platform_usage_percentage' => 50, 'sla_percentage' => 60, 'meetings_expected' => 1, 'meetings_completed' => 1], 6);
        RiskService::recalcular($company);
        $this->assertSame(0, $user->notifications()->count());

        // Muda a prioridade das métricas (sem nenhum dado novo do cliente) e recalcula de novo.
        $company->update(['metric_weights' => [['k' => 'uso', 'peso' => 100]]]);
        RiskService::recalcular($company);

        $this->assertSame(
            0,
            $user->notifications()->where('type', DatabaseNotification::class)
                ->get()->filter(fn ($n) => str_contains($n->data['title'], 'subindo de nível'))->count(),
        );
    }

    public function test_nao_notifica_sobre_cliente_ja_cancelado(): void
    {
        [$company, $user, $cliente] = $this->empresaComUsuario();
        $cliente->update(['status' => 'Cancelado', 'cancelled_at' => '2026-03-01']);

        $this->mes($cliente, '2026-01-01', ['meetings_expected' => 2, 'meetings_completed' => 0], 8);
        RiskService::recalcular($company);

        $this->assertSame(0, $user->notifications()->count());
    }
}
