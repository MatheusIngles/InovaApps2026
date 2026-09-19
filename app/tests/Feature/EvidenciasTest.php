<?php

namespace Tests\Feature;

use App\Filament\Pages\Evidencias;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\User;
use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EvidenciasTest extends TestCase
{
    use RefreshDatabase;

    /** Um cliente que cancela em 2026-07 com uso caindo desde 2026-02, e dois que ficam com métricas boas. */
    private function carteira(): Company
    {
        $company = Company::factory()->create();
        app(CompanyContext::class)->within($company, function () use ($company) {
            $meses = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];
            $cria = function (string $codigo, ?string $saida, callable $uso) use ($company, $meses) {
                $c = Customer::factory()->create(['company_id' => $company->id, 'external_code' => $codigo, 'status' => $saida ? 'Cancelado' : 'Ativo', 'cancelled_at' => $saida ? $saida.'-01' : null]);
                foreach ($meses as $i => $mes) {
                    CustomerMetric::factory()->create(['customer_id' => $c->id, 'reference_month' => $mes.'-01', 'platform_usage_percentage' => $uso($i),
                        'sla_percentage' => 100, 'tickets_opened' => 0, 'tickets_reopened' => 0, 'formal_complaints' => 0, 'payment_delay_days' => 0,
                        'meetings_expected' => 1, 'meetings_completed' => 1]);
                }
            };
            $cria('SAI', '2026-07', fn ($i) => max(10, 90 - $i * 20)); // uso despenca
            $cria('OK1', null, fn () => 90);
            $cria('OK2', null, fn () => 88);
        });

        return $company;
    }

    public function test_backtest_mede_antecedencia_separacao_e_alarme_falso(): void
    {
        $company = $this->carteira();
        $r = app(CompanyContext::class)->within($company, fn () => Backtest::resumo($company));

        $alto = $r['limiares']['medio']; // corte 25
        $this->assertSame(1, $alto['cancelados']);
        $this->assertSame(1, $alto['detectados']); // o cliente que saiu estava em alerta antes de sair
        $this->assertGreaterThanOrEqual(1, $alto['mediana_antecedencia']);
        $this->assertSame(0.0, (float) $alto['alarme_falso_pct']); // os dois que ficaram nunca passam do corte

        // o uso separa perfeitamente quem saiu de quem ficou (AUC 1); reunião não separa (AUC 0,5)
        $this->assertSame(1.0, $r['variaveis']['uso']['auc']);
        $this->assertSame(0.5, $r['variaveis']['reun']['auc']);
        $this->assertEqualsWithDelta(100, array_sum(array_column($r['variaveis'], 'peso_sugerido')), 0.5);
        $this->assertSame(0.0, (float) $r['variaveis']['reun']['peso_sugerido']); // sem separação, sem peso
        $this->assertSame('SAI', $r['cancelamentos']['medio'][0]['codigo']);
    }

    public function test_tela_de_evidencias_renderiza_e_troca_o_corte(): void
    {
        $company = $this->carteira();
        $this->actingAs(User::factory()->for($company)->create());

        $this->get('/evidencias')->assertOk()->assertSee('Antecedência mediana do alerta')->assertSee('Quanto cada variável separa');
        Livewire::test(Evidencias::class)->set('nivel', 'critico')->assertSee('Crítico')->set('nivel', 'invalido')->assertSet('nivel', 'alto');
    }

    public function test_aplicar_ordem_dos_dados_reordena_e_desliga_metricas_sem_separacao(): void
    {
        $company = $this->carteira();
        app(CompanyContext::class)->within($company, fn () => CompanyConfig::aplicarOrdemDosDados($company));

        $pesos = $company->fresh()->pesos();
        $this->assertSame('uso', array_key_first($pesos)); // a que mais separa vem primeiro
        $this->assertSame(0.0, $pesos['reun']); // reunião não separa: desligada
        $this->assertEquals(max(Risco::PESOS), $pesos['uso']);
    }
}
