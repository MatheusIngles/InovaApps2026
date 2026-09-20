<?php

namespace Tests\Feature;

use App\Jobs\ImportarPlanilhaJob;
use App\Livewire\ImportarPlanilha;
use App\Livewire\MetricDefinitions;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\MetricValue;
use App\Models\User;
use App\Support\Import\PlanilhaReader;
use App\Support\Import\TemplateImportService;
use App\Support\Import\TemplateLayout;
use App\Support\Relatorio\RelatorioService;
use App\Support\RiskService;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MetricasDinamicasTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $code, string $month, string $value): array
    {
        return [
            'cliente_id' => $code, 'mes_ref' => $month, 'segmento' => 'Varejo', 'porte' => 'Pequeno',
            'plano' => 'Básico', 'valor_mensal' => '1000', 'sla_contratado_h' => '24',
            'inicio_contrato' => '2025-01-01', 'situacao' => 'Ativo',
            'metrica__pedidos' => $value,
        ];
    }

    private function csv(Company $company, array $rows): string
    {
        $headers = TemplateLayout::headers($company);
        $csv = implode(';', $headers)."\n";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn (string $header): string => (string) ($row[$header] ?? ''), $headers))."\n";
        }

        return $csv;
    }

    private function definition(Company $company, string $direction = 'lower'): void
    {
        $company->metricDefinitions()->create([
            'code' => 'pedidos', 'label' => 'Pedidos recorrentes', 'direction' => $direction,
            'healthy_value' => $direction === 'lower' ? 100 : 0,
            'critical_value' => $direction === 'lower' ? 0 : 100,
            'weight' => 20, 'enabled' => true,
        ]);
    }

    public function test_modelo_e_metrica_sao_especificos_da_empresa(): void
    {
        $first = Company::factory()->create();
        $second = Company::factory()->create();
        $this->definition($first);
        $this->definition($second, 'higher');
        $second->metricDefinitions()->create([
            'code' => 'reclamacoes', 'label' => 'Reclamações', 'direction' => 'higher',
            'healthy_value' => 0, 'critical_value' => 10, 'weight' => 5, 'enabled' => true,
        ]);

        $this->actingAs(User::factory()->for($first)->create());
        $this->get(route('planilha.modelo'))->assertOk()->assertDownload('seer-modelo-livre-'.$first->slug.'.csv');
        $this->assertStringContainsString('metrica__pedidos', TemplateLayout::csv($first));
        $this->assertSame(1, $first->metricDefinitions()->count());
        app(CompanyContext::class)->within($second, fn () => $this->assertSame(2, $second->metricDefinitions()->count()));
        $this->assertNotSame(TemplateLayout::headers($first), TemplateLayout::headers($second));

        $this->actingAs(User::factory()->for($second)->create());
        $this->withSession(['company_id' => $second->id]);
        $this->get(route('planilha.modelo'))->assertOk()->assertDownload('seer-modelo-livre-'.$second->slug.'.csv');
        $this->assertSame('higher', $second->metricDefinitions()->first()->direction);

        app(CompanyContext::class)->within($first, fn () => TemplateImportService::importar($first, [
            'cabecalhos' => TemplateLayout::headers($first),
            'linhas' => [$this->row('A', '2026-06', '20')],
        ]));
        app(CompanyContext::class)->within($second, fn () => TemplateImportService::importar($second, [
            'cabecalhos' => TemplateLayout::headers($second),
            'linhas' => [$this->row('A', '2026-06', '20')],
        ]));
        app(CompanyContext::class)->within($first, fn () => $this->assertSame(80, Customer::firstOrFail()->score));
        app(CompanyContext::class)->within($second, fn () => $this->assertSame(20, Customer::firstOrFail()->score));
    }

    public function test_metrica_nova_influencia_score_e_reimportacao_atualiza_sem_duplicar(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);

        $path = tempnam(sys_get_temp_dir(), 'seer').'.csv';
        file_put_contents($path, $this->csv($company, [$this->row('A', '2026-06', '20')]));
        $table = PlanilhaReader::lerModelo($path, 'csv');
        $stats = TemplateImportService::importar($company, $table);

        $this->assertSame(1, $stats['valores_metricas']);
        app(CompanyContext::class)->within($company, function (): void {
            $customer = Customer::firstOrFail();
            $this->assertSame(80, $customer->score);
            $this->assertSame('Pedidos recorrentes', $customer->sinais[0]['label']);
            $this->assertSame(1, MetricValue::count());
        });
        $this->actingAs(User::factory()->for($company)->create())
            ->get('/empresas/A')->assertOk()->assertSee('Pedidos recorrentes')->assertSee('<h3>Métricas</h3>', false);

        file_put_contents($path, $this->csv($company, [$this->row('A', '2026-06', '100')]));
        TemplateImportService::importar($company, PlanilhaReader::lerModelo($path, 'csv'));
        app(CompanyContext::class)->within($company, function (): void {
            $this->assertSame(0, Customer::firstOrFail()->score);
            $this->assertSame(1, MetricValue::count());
        });

        $empty = $this->row('A', '2026-06', '');
        file_put_contents($path, $this->csv($company, [$empty]));
        TemplateImportService::importar($company, PlanilhaReader::lerModelo($path, 'csv'));
        app(CompanyContext::class)->within($company, function (): void {
            $this->assertSame(0, MetricValue::count());
            $this->assertNull(Customer::firstOrFail()->currentAssessment);
        });
    }

    public function test_modelo_fora_do_padrao_ou_linha_invalida_nao_grava_clientes(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        $valid = $this->row('A', '2026-06', '20');

        try {
            TemplateImportService::importar($company, [
                'cabecalhos' => ['cliente_id', 'mes_ref', 'outra_coluna'],
                'linhas' => [$valid],
            ]);
            $this->fail('O cabeçalho inválido deveria ser recusado.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('fora do padrão', $e->getMessage());
        }

        try {
            TemplateImportService::importar($company, [
                'cabecalhos' => TemplateLayout::headers($company),
                'linhas' => [$valid, $this->row('B', '2026-07', 'texto')],
            ]);
            $this->fail('A segunda linha inválida deveria cancelar toda a importação.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Linha 3', $e->getMessage());
        }

        $differentProfile = $this->row('A', '2026-07', '30');
        $differentProfile['plano'] = 'Outro';
        try {
            TemplateImportService::importar($company, [
                'cabecalhos' => TemplateLayout::headers($company),
                'linhas' => [$valid, $differentProfile],
            ]);
            $this->fail('Dados conflitantes do mesmo cliente deveriam cancelar a importação.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('dados do contrato divergentes', $e->getMessage());
        }
        $this->assertSame(0, $company->customers()->count());
        $this->assertSame(0, MetricValue::count());
        $invalidMonth = $this->row('C', '2026-06-02', '20');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mes_ref em AAAA-MM');
        TemplateImportService::importar($company, [
            'cabecalhos' => TemplateLayout::headers($company),
            'linhas' => [$invalidMonth],
        ]);
    }

    public function test_upload_da_interface_identifica_cabecalho_livre_e_confirma_metrica_nova(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $this->definition($company);
        $this->actingAs(User::factory()->for($company)->create());

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('carteira.csv', "cliente_id;mes_ref;outro\nA;2026-06;2\n"))
            ->assertSet('cabecalhos', ['cliente_id', 'mes_ref', 'outro'])
            ->assertSet('metricMappings.0.column', 'outro');

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('modelo.csv', "cliente_id;mes_ref;segmento;porte;plano;valor_mensal;metrica__pedidos\nA;2026-06;Varejo;Pequeno;Básico;1000;20\n"))
            ->call('importar')
            ->assertRedirect('/');
        $this->assertSame(1, $company->customers()->count());
    }

    public function test_job_recusa_linha_invalida_e_notifica_sem_gravar(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $this->definition($company);
        $user = User::factory()->for($company)->create();
        Storage::put('imports/invalido.csv', $this->csv($company, [$this->row('A', '2026-06', 'abc')]));

        (new ImportarPlanilhaJob($company->id, $user->id, 'imports/invalido.csv', 'csv', [], true))->handle();

        $this->assertSame(0, $company->customers()->count());
        $this->assertSame('Importação não concluída', $user->notifications->first()->data['title']);
        Storage::assertMissing('imports/invalido.csv');
    }

    public function test_planilha_do_desafio_nao_e_aceita_como_modelo_de_upload(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('uma única aba');

        PlanilhaReader::lerModelo(database_path('data/INOVAAPPS_base_de_dados.xlsx'), 'xlsx');
    }

    public function test_modelo_xlsx_de_uma_aba_e_aceito(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        $headers = TemplateLayout::headers($company);
        $row = $this->row('X1', '2026-06', '50');
        $path = tempnam(sys_get_temp_dir(), 'seer').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        $cells = array_map(fn (string $header): string => (string) ($row[$header] ?? ''), $headers);
        $cells[array_search('mes_ref', $headers, true)] = new \DateTimeImmutable('2026-06-01');
        $writer->addRow(Row::fromValuesWithStyles($cells, null, [array_search('mes_ref', $headers, true) => (new Style)->setFormat('yyyy-mm-dd')]));
        $writer->close();

        $table = PlanilhaReader::lerModelo($path, 'xlsx');
        $this->assertSame('2026-06-01', $table['linhas'][0]['mes_ref']);
        $result = TemplateImportService::importar($company, $table);
        $this->assertSame(1, $result['valores_metricas']);
        app(CompanyContext::class)->within($company, fn () => $this->assertSame(50, Customer::firstOrFail()->score));
    }

    public function test_desativar_metrica_preserva_valores_mas_remove_avaliacao_sem_outros_sinais(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        TemplateImportService::importar($company, [
            'cabecalhos' => TemplateLayout::headers($company),
            'linhas' => [$this->row('A', '2026-06', '20')],
        ]);
        $company->metricDefinitions()->firstOrFail()->update(['enabled' => false]);
        RiskService::recalcular($company);

        app(CompanyContext::class)->within($company, function (): void {
            $this->assertSame(1, MetricValue::count());
            $this->assertNull(Customer::firstOrFail()->currentAssessment);
        });
    }

    public function test_desativar_metrica_remove_avaliacao_mais_recente_quando_ha_sinais_antigos(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        TemplateImportService::importar($company, [
            'cabecalhos' => TemplateLayout::headers($company),
            'linhas' => [$this->row('A', '2026-06', '20')],
        ]);
        $customer = $company->customers()->firstOrFail();
        CustomerMetric::factory()->for($customer)->create(['reference_month' => '2026-05-01']);
        RiskService::recalcular($company);

        $company->metricDefinitions()->firstOrFail()->update(['enabled' => false]);
        RiskService::recalcular($company);

        app(CompanyContext::class)->within($company, fn () => $this->assertSame(
            '2026-05',
            Customer::firstOrFail()->currentAssessment->reference_month->format('Y-m'),
        ));
    }

    public function test_metrica_com_peso_zero_nao_cria_mes_de_avaliacao(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        TemplateImportService::importar($company, [
            'cabecalhos' => TemplateLayout::headers($company),
            'linhas' => [$this->row('A', '2026-06', '20')],
        ]);
        $customer = $company->customers()->firstOrFail();
        CustomerMetric::factory()->for($customer)->create(['reference_month' => '2026-05-01']);
        $company->metricDefinitions()->firstOrFail()->update(['weight' => 0]);

        RiskService::recalcular($company);

        app(CompanyContext::class)->within($company, function (): void {
            $customer = Customer::firstOrFail();
            $this->assertSame('2026-05', $customer->currentAssessment->reference_month->format('Y-m'));
            $this->assertSame(1, MetricValue::count());
        });
    }

    public function test_factory_de_valor_mantem_cliente_e_definicao_na_mesma_empresa(): void
    {
        $value = MetricValue::factory()->create();

        $this->assertSame($value->company_id, $value->customer->company_id);
        $this->assertSame($value->company_id, $value->definition->company_id);
    }

    public function test_backtest_da_metrica_propria_considera_apenas_clientes_com_observacao(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        $cancelled = $this->row('A', '2026-06', '20');
        $cancelled['situacao'] = 'Cancelado';
        $cancelled['mes_cancelamento'] = '2026-07';
        $cancelled['chamados_abertos'] = '1';

        TemplateImportService::importar($company, [
            'cabecalhos' => TemplateLayout::headers($company),
            'linhas' => [$cancelled, $this->row('B', '2026-06', '100'), $this->row('C', '2026-06', '')],
        ]);

        $summary = app(CompanyContext::class)->within($company, fn () => Backtest::resumo($company));
        $metric = $summary['metricas_proprias']['custom:pedidos'];
        $this->assertSame(1.0, $metric['auc']);
        $this->assertSame(1, $metric['cancelados']);
        $this->assertSame(1, $metric['retidos']);
        $this->assertSame(0.8, $metric['media_cancelados']);
        $this->assertSame(0.0, $metric['media_retidos']);
        $pdf = app(CompanyContext::class)->within($company, fn () => RelatorioService::carteira($company));
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_cadastro_e_edicao_validam_direcao_e_empresa(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $this->actingAs(User::factory()->for($company)->create());
        $this->get('/configuracoes')->assertRedirect('/planilha');

        Livewire::test(MetricDefinitions::class)
            ->set('newMetric.code', 'pedidos')
            ->set('newMetric.label', 'Pedidos recorrentes')
            ->set('newMetric.description', 'Número mensal de pedidos recorrentes')
            ->set('newMetric.direction', 'lower')
            ->set('newMetric.healthy_value', 100)
            ->set('newMetric.critical_value', 0)
            ->set('newMetric.weight', 20)
            ->call('create')
            ->assertHasNoErrors();

        $definition = $company->metricDefinitions()->firstOrFail();
        $this->assertSame('pedidos', $definition->code);
        $this->assertSame(0, $other->metricDefinitions()->count());

        Livewire::test(MetricDefinitions::class)
            ->set("edits.{$definition->id}.critical_value", 150)
            ->call('saveDefinition', $definition->id)
            ->assertHasErrors("edits.{$definition->id}.critical_value");
    }
}
