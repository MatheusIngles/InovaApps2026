<?php

namespace Tests\Feature;

use App\Filament\Pages\Configuracoes;
use App\Jobs\ImportarPlanilhaJob;
use App\Livewire\ImportarPlanilha;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPeriod;
use App\Models\MetricValue;
use App\Models\User;
use App\Support\Import\DynamicImportService;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ImportacaoDinamicaTest extends TestCase
{
    use RefreshDatabase;

    private function table(array $rows, array $metrics): array
    {
        return ['cabecalhos' => [...array_keys(DynamicImportService::STRUCTURE), ...$metrics], 'linhas' => $rows];
    }

    private function row(string $code, string $month, array $metrics): array
    {
        return ['cliente_id' => $code, 'mes_ref' => $month, 'segmento' => 'Varejo', 'porte' => 'Pequeno', 'plano' => 'Básico', 'valor_mensal' => '1000'] + $metrics;
    }

    private function newMetric(string $column, string $code, string $type = 'decimal', string $direction = 'higher'): array
    {
        return [
            'column' => $column, 'target' => 'new', 'code' => $code, 'label' => ucfirst($code),
            'description' => 'Medição mensal de '.$code.'.', 'value_type' => $type,
            'direction' => $direction, 'healthy_value' => $direction === 'higher' ? 0 : 100,
            'critical_value' => $direction === 'higher' ? 100 : 0, 'weight' => 20,
        ];
    }

    public function test_conta_nova_descobre_metricas_sem_cadastro_previo_e_aceita_valores_ausentes(): void
    {
        $company = Company::factory()->create();
        $this->assertSame(0, $company->metricDefinitions()->count());
        $table = $this->table([
            $this->row('A', '2026-06', ['faturamento' => '80', 'churn' => '']),
            $this->row('B', '2026-06', ['faturamento' => '20', 'churn' => '90']),
        ], ['faturamento', 'churn']);
        $suggestion = DynamicImportService::sugerir($company, $table['cabecalhos'], $table['linhas']);
        $this->assertSame(array_keys(DynamicImportService::STRUCTURE), array_values($suggestion['structure']));
        $this->assertSame(['faturamento', 'churn'], array_column($suggestion['metrics'], 'column'));

        $result = DynamicImportService::importar($company, $table, $suggestion['structure'], [
            $this->newMetric('faturamento', 'faturamento', direction: 'lower'),
            $this->newMetric('churn', 'churn'),
        ]);

        $this->assertSame(2, $result['novas_metricas']);
        $this->assertSame(3, $result['valores_metricas']);
        $this->assertSame(2, $company->metricDefinitions()->count());
        app(CompanyContext::class)->within($company, function (): void {
            $a = Customer::where('external_code', 'A')->firstOrFail();
            $b = Customer::where('external_code', 'B')->firstOrFail();
            $this->assertSame(20, $a->score);
            $this->assertSame(85, $b->score);
            $this->assertSame(1, $a->metricValues()->count());
            $this->assertSame(2, $b->metricValues()->count());
        });
    }

    public function test_segunda_planilha_adiciona_metrica_sem_apagar_coluna_omitida(): void
    {
        $company = Company::factory()->create();
        $structure = array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE));
        $first = $this->table([$this->row('A', '2026-06', ['faturamento' => '50'])], ['faturamento']);
        DynamicImportService::importar($company, $first, $structure, [$this->newMetric('faturamento', 'faturamento')]);
        $second = $this->table([$this->row('A', '2026-06', ['churn' => '30'])], ['churn']);
        DynamicImportService::importar($company, $second, $structure, [$this->newMetric('churn', 'churn')]);
        $this->assertSame(2, $company->metricDefinitions()->count());
        app(CompanyContext::class)->within($company, fn () => $this->assertSame(2, MetricValue::count()));

        $third = $this->table([
            $this->row('A', '2026-06', ['churn' => '']),
            $this->row('B', '2026-06', ['churn' => '10']),
        ], ['churn']);
        $definition = $company->metricDefinitions()->where('code', 'churn')->firstOrFail();
        DynamicImportService::importar($company, $third, $structure, [['column' => 'churn', 'target' => (string) $definition->id]]);
        app(CompanyContext::class)->within($company, function (): void {
            $values = MetricValue::whereHas('customer', fn ($query) => $query->where('external_code', 'A'))->with('definition')->get();
            $this->assertCount(1, $values);
            $this->assertSame('faturamento', $values->first()->definition->code);
        });
    }

    public function test_linha_invalida_nao_cadastra_metricas_nem_clientes(): void
    {
        $company = Company::factory()->create();
        $table = $this->table([
            $this->row('A', '2026-06', ['churn' => '10']),
            $this->row('B', '2026-07', ['churn' => 'invalido']),
        ], ['churn']);

        try {
            DynamicImportService::importar($company, $table,
                array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE)),
                [$this->newMetric('churn', 'churn')]);
            $this->fail('Uma linha inválida deve cancelar toda a importação.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Linha 3', $e->getMessage());
        }
        $this->assertSame(0, $company->metricDefinitions()->count());
        $this->assertSame(0, $company->customers()->count());
    }

    public function test_upload_mostra_mapeamento_e_importa_apos_confirmacao(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->for($company)->create());
        $this->get(route('planilha.modelo'))->assertOk()->assertDownload('seer-modelo-livre-'.$company->slug.'.csv');

        $csv = "cliente_id;mes_ref;segmento;porte;plano;valor_mensal;churn\nA;2026-06;Varejo;Pequeno;Básico;1000;50\n";
        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('livre.csv', $csv))
            ->assertSet('metricMappings.0.column', 'churn')
            ->set('metricMappings.0.description', 'Taxa de cancelamento mensal')
            ->set('metricMappings.0.direction', 'higher')
            ->set('metricMappings.0.healthy_value', 0)
            ->set('metricMappings.0.critical_value', 100)
            ->call('importar')
            ->assertRedirect('/');

        $this->assertSame(1, $company->metricDefinitions()->count());
        $this->assertSame(1, $company->customers()->count());
    }

    public function test_job_com_mapeamento_dinamico_isola_empresa(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        Storage::put('imports/livre.csv', "cliente_id;mes_ref;segmento;porte;plano;valor_mensal;churn\nA;2026-06;Varejo;Pequeno;Básico;1000;50\n");

        (new ImportarPlanilhaJob($company->id, $user->id, 'imports/livre.csv', 'csv', [], false,
            array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE)),
            [$this->newMetric('churn', 'churn')]))->handle();

        $this->assertSame(1, $company->metricDefinitions()->count());
        $this->assertSame(0, $other->metricDefinitions()->count());
        $this->assertSame(1, $company->customers()->count());
        $this->assertSame(0, $other->customers()->count());
    }

    public function test_tipos_textual_moeda_e_percentual_e_mes_sem_valores_permanecem_no_historico(): void
    {
        $company = Company::factory()->create();
        $structure = array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE));
        $table = $this->table([
            $this->row('A', '2026-06', ['receita' => 'R$ 1.200,50', 'conversao' => '35%', 'comentario' => 'Em expansão']),
            $this->row('A', '2026-07', ['receita' => '', 'conversao' => '', 'comentario' => '']),
        ], ['receita', 'conversao', 'comentario']);
        DynamicImportService::importar($company, $table, $structure, [
            $this->newMetric('receita', 'receita', 'currency', 'lower'),
            $this->newMetric('conversao', 'conversao', 'percentage', 'lower'),
            $this->newMetric('comentario', 'comentario', 'text'),
        ]);

        app(CompanyContext::class)->within($company, function (): void {
            $this->assertSame(2, CustomerPeriod::count());
            $this->assertSame(3, MetricValue::count());
            $this->assertSame('1200.5000', MetricValue::whereHas('definition', fn ($query) => $query->where('code', 'receita'))->firstOrFail()->value);
            $this->assertSame('Em expansão', MetricValue::whereHas('definition', fn ($query) => $query->where('code', 'comentario'))->firstOrFail()->text_value);
        });
        $this->actingAs(User::factory()->for($company)->create())
            ->get('/empresas/A')->assertOk()->assertSee('2026-07')->assertSee('Em expansão')->assertSee('1.200,50');
    }

    public function test_mapeamento_rejeita_metrica_de_outra_conta_sem_gravar(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $foreign = $other->metricDefinitions()->create([
            'code' => 'churn', 'label' => 'Churn', 'description' => 'Taxa de churn', 'value_type' => 'decimal',
            'direction' => 'higher', 'healthy_value' => 0, 'critical_value' => 100, 'weight' => 20, 'enabled' => true,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não pertence a esta empresa');
        DynamicImportService::importar($company,
            $this->table([$this->row('A', '2026-06', ['churn' => '50'])], ['churn']),
            array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE)),
            [['column' => 'churn', 'target' => (string) $foreign->id]],
        );
    }

    public function test_configuracoes_da_conta_dinamica_salvam_limiares_sem_pesos_legados(): void
    {
        $company = Company::factory()->create();
        DynamicImportService::importar($company,
            $this->table([$this->row('A', '2026-06', ['churn' => '50'])], ['churn']),
            array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE)),
            [$this->newMetric('churn', 'churn')],
        );
        $this->actingAs(User::factory()->for($company)->create());

        $this->get('/configuracoes')->assertOk()->assertDontSee('Prioridade dos sinais padrão')->assertSee('Equilíbrio da fila');
        Livewire::test(Configuracoes::class)->set('data.limiares.critico', 70)->set('data.prioridade', 25)->call('salvar')->assertHasNoErrors();

        $this->assertSame(70, $company->fresh()->limiares()['critico']);
        $this->assertSame(25, $company->fresh()->prioridadeK());
        $this->assertNull($company->fresh()->metric_weights);
    }

    public function test_valor_mensal_e_obrigatorio_e_atualiza_o_cliente_sem_apagar_o_historico(): void
    {
        $this->assertStringContainsString('valor_mensal', DynamicImportService::csvModelo());
        $company = Company::factory()->create();
        $structure = array_combine(array_keys(DynamicImportService::STRUCTURE), array_keys(DynamicImportService::STRUCTURE));
        $first = $this->table([$this->row('A', '2026-06', ['churn' => '50'])], ['churn']);
        unset($first['linhas'][0]['valor_mensal']);

        try {
            DynamicImportService::importar($company, $first, $structure, [$this->newMetric('churn', 'churn')]);
            $this->fail('Valor mensal ausente deveria impedir a importação.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('valor_mensal', $e->getMessage());
        }
        $this->assertSame(0, $company->customers()->count());

        $first['linhas'][0]['valor_mensal'] = 'R$ 1.200,50';
        DynamicImportService::importar($company, $first, $structure, [$this->newMetric('churn', 'churn')]);
        $definition = $company->metricDefinitions()->firstOrFail();
        $second = $this->table([$this->row('A', '2026-07', ['churn' => '60'])], ['churn']);
        $second['linhas'][0]['valor_mensal'] = '1500';
        DynamicImportService::importar($company, $second, $structure, [['column' => 'churn', 'target' => (string) $definition->id]]);

        app(CompanyContext::class)->within($company, function (): void {
            $this->assertSame('1500.00', Customer::firstOrFail()->monthly_value);
            $this->assertSame(['1200.50', '1500.00'], CustomerPeriod::orderBy('reference_month')->pluck('monthly_value')->all());
        });
    }
}
