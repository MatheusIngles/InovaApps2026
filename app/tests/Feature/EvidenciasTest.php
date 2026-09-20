<?php

namespace Tests\Feature;

use App\Filament\Widgets\InsatisfacaoChart;
use App\Jobs\GerarRelatorioCarteiraJob;
use App\Jobs\GerarRelatorioEmpresaJob;
use App\Livewire\PainelSegmentos;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\User;
use App\Support\Relatorio\RelatorioService;
use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use App\Support\Validacao\Previsao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EvidenciasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Livewire::withoutLazyLoading();
    }

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

        $medio = $r['limiares']['medio']; // corte 25
        $this->assertSame(1, $medio['cancelados']);
        $this->assertSame(1, $medio['detectados']); // o cliente que saiu estava em alerta antes de sair
        $this->assertGreaterThanOrEqual(1, $medio['mediana_antecedencia']);
        $this->assertSame(0.0, (float) $medio['alarme_falso_pct']); // os dois que ficaram nunca passam do corte

        // o uso separa perfeitamente quem saiu de quem ficou (AUC 1); reunião não separa (AUC 0,5)
        $this->assertSame(1.0, $r['variaveis']['uso']['auc']);
        $this->assertSame(0.5, $r['variaveis']['reun']['auc']);
        $this->assertEqualsWithDelta(100, array_sum(array_column($r['variaveis'], 'peso_sugerido')), 0.5);
        $this->assertSame(0.0, (float) $r['variaveis']['reun']['peso_sugerido']); // sem separação, sem peso
        $this->assertSame('SAI', $r['cancelamentos']['medio'][0]['codigo']);
        $this->assertFalse($r['evidencia_suficiente']); // 1 cancelamento não calibra nada
    }

    public function test_painel_renderiza_segmentos_com_evidencias_e_gera_relatorio_da_carteira(): void
    {
        $company = $this->carteira();
        $this->actingAs(User::factory()->for($company)->create());

        $this->get('/painel')->assertOk()->assertSee('Por segmento')->assertSee('Gerar relatório de evidências');
        Livewire::test(PainelSegmentos::class)->call('$refresh')->assertSee('Cancelamentos por segmento')->assertSee('Evidências da carteira');
        $this->assertStringStartsWith('%PDF', RelatorioService::carteira($company));
    }

    public function test_relatorio_da_carteira_roda_na_fila_e_guarda_o_pdf_para_download(): void
    {
        Storage::fake('local');
        $company = $this->carteira();
        $user = User::factory()->for($company)->create();
        $arquivo = (string) Str::uuid();

        (new GerarRelatorioCarteiraJob($company->id, $user->id, $arquivo))->handle();

        Storage::disk('local')->assertExists(GerarRelatorioEmpresaJob::caminho($company->id, $user->id, $arquivo));
        $this->actingAs($user)->get(route('relatorios.download', ['arquivo' => $arquivo]))->assertOk();
    }

    public function test_validacao_temporal_calibra_ate_o_corte_e_testa_nos_cancelamentos_seguintes(): void
    {
        $company = Company::factory()->create();
        app(CompanyContext::class)->within($company, function () use ($company) {
            $meses = collect(range(0, 17))->map(fn (int $i) => date('Y-m', strtotime('2025-01-01 +'.$i.' month')))->all();
            $cria = function (string $codigo, ?int $saida) use ($company, $meses) {
                $c = Customer::factory()->create(['company_id' => $company->id, 'external_code' => $codigo, 'status' => $saida ? 'Cancelado' : 'Ativo', 'cancelled_at' => $saida ? $meses[$saida].'-01' : null]);
                foreach ($meses as $i => $mes) {
                    CustomerMetric::factory()->create(['customer_id' => $c->id, 'reference_month' => $mes.'-01', 'platform_usage_percentage' => $saida && $i >= $saida - 3 ? 15 : 90,
                        'sla_percentage' => 100, 'tickets_opened' => 0, 'tickets_reopened' => 0, 'formal_complaints' => 0, 'payment_delay_days' => 0,
                        'meetings_expected' => 1, 'meetings_completed' => 1]);
                }
            };
            foreach ([9, 10, 11, 10, 11] as $i => $saida) {
                $cria("T$i", $saida); // 5 cancelamentos em 2025 (calibração)
            }
            foreach ([15, 16, 17] as $i => $saida) {
                $cria("V$i", $saida); // 3 cancelamentos em 2026 (teste)
            }
            foreach (range(1, 6) as $i) {
                $cria("OK$i", null);
            }
        });

        $v = app(CompanyContext::class)->within($company, fn () => Backtest::resumo($company))['validacao_temporal'];

        $this->assertTrue($v['suficiente']);
        $this->assertSame('2025-12', $v['corte']);
        $this->assertSame(5, $v['treino']['cancelados']);
        $this->assertSame(3, $v['teste']['cancelados']);
        $this->assertGreaterThan(0.9, $v['auc']['teste_pesos_treino']); // o padrão aprendido em 2025 vale em 2026
        $this->assertSame(3, $v['detectados']);
        $this->assertSame(0.0, $v['alarme_falso_pct']);
        $this->assertGreaterThan(0, $v['pesos_treino']['uso']);
    }

    public function test_validacao_temporal_fica_insuficiente_sem_cancelamentos_dos_dois_lados(): void
    {
        $company = $this->carteira(); // 1 cancelamento só

        $v = app(CompanyContext::class)->within($company, fn () => Backtest::resumo($company))['validacao_temporal'];

        $this->assertFalse($v['suficiente']);
    }

    public function test_previsao_do_proximo_mes_segue_a_tendencia(): void
    {
        $sobe = Previsao::proximoMes([10, 20, 30, 40]);
        $this->assertSame(50, $sobe['valor']);
        $this->assertSame(10.0, $sobe['tendencia']);
        $this->assertSame(100, Previsao::proximoMes([60, 80, 100])['valor']); // 120 é limitado a 100
        $this->assertNull(Previsao::proximoMes([40, 50])); // poucos meses
        $this->assertSame(30, Previsao::proximoMes([30, 30, 30, 30])['valor']);
    }

    public function test_grafico_de_insatisfacao_projeta_o_mes_seguinte_so_para_ativos(): void
    {
        $company = $this->carteira();
        $this->actingAs(User::factory()->for($company)->create());
        app(CompanyContext::class)->set($company);

        $ativo = InsatisfacaoChart::serie(Customer::where('external_code', 'OK1')->first());
        $this->assertNotNull($ativo['previsao']);
        $this->assertCount(4, $ativo['scores']); // 6 meses: a série começa no 3º
        $this->assertNull(InsatisfacaoChart::serie(Customer::where('external_code', 'SAI')->first())['previsao']); // cancelado: sem previsão

        $this->get('/empresas/OK1')->assertOk()->assertSee('Tendência e previsão')->assertSee('no próximo mês');
    }

    public function test_configuracao_base_dos_dados_nao_se_aplica_sem_evidencia_nem_sobre_personalizacao(): void
    {
        $company = $this->carteira(); // 1 cancelado: evidência insuficiente
        $this->assertFalse(app(CompanyContext::class)->within($company, fn () => CompanyConfig::aplicarBaseDosDados($company)));
        $this->assertNull($company->fresh()->metric_weights);

        $company->update(['level_thresholds' => ['critico' => 60, 'alto' => 45, 'medio' => 30]]); // já personalizou
        $this->assertFalse(app(CompanyContext::class)->within($company, fn () => CompanyConfig::aplicarBaseDosDados($company->fresh())));
    }

    public function test_cortes_sugeridos_ficam_em_ordem_crescente_com_espaco_entre_niveis(): void
    {
        $company = $this->carteira();
        $l = app(CompanyContext::class)->within($company, fn () => (new Backtest($company->pesos()))->limiaresSugeridos());

        $this->assertLessThanOrEqual($l['alto'] - 5, $l['medio']);
        $this->assertLessThanOrEqual($l['critico'] - 5, $l['alto']);
    }

    public function test_aplicar_configuracao_dos_dados_ordena_por_evidencia_quando_ha_cancelamentos_suficientes(): void
    {
        $company = $this->carteira();
        app(CompanyContext::class)->within($company, function () use ($company) {
            foreach (range(3, 6) as $i) { // mais retidos: agora há evidência dos dois lados
                $r = Customer::factory()->create(['company_id' => $company->id, 'external_code' => "OK$i", 'status' => 'Ativo']);
                foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'] as $mes) {
                    CustomerMetric::factory()->create(['customer_id' => $r->id, 'reference_month' => $mes.'-01', 'platform_usage_percentage' => 90, 'sla_percentage' => 100,
                        'tickets_opened' => 0, 'tickets_reopened' => 0, 'formal_complaints' => 0, 'payment_delay_days' => 0, 'meetings_expected' => 1, 'meetings_completed' => 1]);
                }
            }
            foreach (range(1, 5) as $i) { // mais 5 cancelados iguais ao primeiro
                $c = Customer::factory()->create(['company_id' => $company->id, 'external_code' => "S$i", 'status' => 'Cancelado', 'cancelled_at' => '2026-07-01']);
                foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'] as $k => $mes) {
                    CustomerMetric::factory()->create(['customer_id' => $c->id, 'reference_month' => $mes.'-01', 'platform_usage_percentage' => max(10, 90 - $k * 20),
                        'sla_percentage' => 100, 'tickets_opened' => 0, 'tickets_reopened' => 0, 'formal_complaints' => 0, 'payment_delay_days' => 0, 'meetings_expected' => 1, 'meetings_completed' => 1]);
                }
            }
            $this->assertTrue(CompanyConfig::aplicarConfiguracaoDosDados($company));
        });

        $pesos = $company->fresh()->pesos();
        $this->assertSame('uso', array_key_first($pesos)); // a que mais separa vem primeiro
        $this->assertEquals(0, $pesos['reun']); // reunião não separa: desligada
        $this->assertEquals(max(Risco::PESOS), $pesos['uso']);
        $this->assertNotNull($company->fresh()->level_thresholds); // cortes calibrados pela carteira
    }
}
