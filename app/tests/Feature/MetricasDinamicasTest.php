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
use App\Support\Import\DynamicImportService;
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
use OpenSpout\Reader\XLSX\Reader;
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

    /** @param  array<string, list<list<mixed>>>  $abas */
    private function xlsx(array $abas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'seer').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $primeira = true;
        foreach ($abas as $nome => $linhas) {
            $primeira ? $writer->getCurrentSheet()->setName($nome) : $writer->addNewSheetAndMakeItCurrent()->setName($nome);
            $primeira = false;
            foreach ($linhas as $linha) {
                $writer->addRow(Row::fromValues($linha));
            }
        }
        $writer->close();

        return $path;
    }

    public function test_planilha_do_desafio_com_varias_abas_e_ligada_por_cliente_id_e_o_dicionario_preenche_as_metricas(): void
    {
        $company = Company::factory()->create();
        $tabela = PlanilhaReader::lerModelo(database_path('data/INOVAAPPS_base_de_dados.xlsx'), 'xlsx');

        $this->assertSame(['cliente_id', 'mes_ref'], array_slice($tabela['cabecalhos'], 0, 2));
        foreach (['segmento', 'valor_mensal', 'chamados_abertos', 'nota_nps', 'situacao', 'mes_cancelamento'] as $coluna) {
            $this->assertContains($coluna, $tabela['cabecalhos']);
        }
        $this->assertArrayHasKey('pct_sla_cumprido', $tabela['dicionario']);
        $this->assertArrayNotHasKey('Leia-me', $tabela['dicionario']);

        $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], array_slice($tabela['linhas'], 0, 5), $tabela['dicionario']);
        $tipos = array_column($sugestao['metrics'], 'value_type', 'column');
        $this->assertSame('integer', $tipos['chamados_abertos']);
        $this->assertSame('percentage', $tipos['pct_sla_cumprido']);
        $this->assertSame('binary', $tipos['reunioes_previstas']);
        $this->assertSame('grade', $tipos['nota_nps']);
        foreach (['inicio_contrato', 'situacao', 'mes_cancelamento'] as $reconhecida) {
            $this->assertArrayNotHasKey($reconhecida, $tipos); // viram dados do cliente, não métricas
        }
        $this->assertSame('text', $tipos['classificacao_nps']);
        $descricoes = array_column($sugestao['metrics'], 'description', 'column');
        $this->assertNotSame('', $descricoes['chamados_abertos']);
    }

    public function test_situacao_e_mes_cancelamento_marcam_o_cliente_como_cancelado_e_o_historico_para_no_cancelamento(): void
    {
        $company = Company::factory()->create();
        $path = $this->xlsx([
            'clientes' => [
                ['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal', 'inicio_contrato', 'situacao', 'mes_cancelamento'],
                ['R1', 'Pizzaria', 'Pequeno', 'Basico', 100, '2024-05-01', 'Cancelado', '2026-03'],
                ['R2', 'Pizzaria', 'Pequeno', 'Basico', 100, '2024-06-01', 'Ativo', null],
            ],
            'metricas' => [
                ['cliente_id', 'mes_ref', 'pedidos'],
                ['R1', '2026-01', 900], ['R1', '2026-02', 700], ['R1', '2026-03', 50],
                ['R2', '2026-01', 900], ['R2', '2026-02', 910], ['R2', '2026-03', 905],
            ],
        ]);
        $tabela = PlanilhaReader::lerModelo($path, 'xlsx');
        $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
        $this->assertSame(['pedidos'], array_column($sugestao['metrics'], 'column'));

        DynamicImportService::importar($company, $tabela, $sugestao['structure'], array_map(fn (array $m): array => ['direction' => 'lower', 'healthy_value' => 800, 'critical_value' => 100, 'weight' => 20, 'description' => 'Pedidos'] + $m, $sugestao['metrics']));

        app(CompanyContext::class)->within($company, function (): void {
            $r1 = Customer::where('external_code', 'R1')->firstOrFail();
            $this->assertSame('Cancelado', $r1->status);
            $this->assertSame('2026-03', $r1->cancelled_at->format('Y-m'));
            $this->assertSame('2024-05-01', $r1->contract_started_at->toDateString());
            $this->assertSame('Ativo', Customer::where('external_code', 'R2')->firstOrFail()->status);
            $this->assertSame(['R2'], Customer::ativas()->pluck('external_code')->all());
            $this->assertSame('2026-02', $r1->currentAssessment->reference_month->format('Y-m')); // o mês do cancelamento fica de fora
        });
    }

    public function test_situacao_invalida_ou_cancelado_sem_mes_e_recusada(): void
    {
        $company = Company::factory()->create();
        foreach ([['Talvez', null, 'situacao deve ser'], ['Cancelado', null, 'informe o mes_cancelamento'], ['Ativo', '2026-03', 'não pode ter mes_cancelamento']] as [$situacao, $mes, $mensagem]) {
            $path = $this->xlsx([
                'clientes' => [['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal', 'situacao', 'mes_cancelamento'], ['R1', 'P', 'P', 'B', 100, $situacao, $mes]],
                'metricas' => [['cliente_id', 'mes_ref', 'pedidos'], ['R1', '2026-01', 900]],
            ]);
            $tabela = PlanilhaReader::lerModelo($path, 'xlsx');
            $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
            try {
                DynamicImportService::importar($company, $tabela, $sugestao['structure'], array_map(fn (array $m): array => ['direction' => 'lower', 'healthy_value' => 800, 'critical_value' => 100, 'weight' => 20, 'description' => 'Pedidos'] + $m, $sugestao['metrics']));
                $this->fail("Aceitou situação {$situacao}.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($mensagem, $e->getMessage());
            }
        }
    }

    public function test_tela_de_confirmacao_mostra_cartoes_por_metrica_e_permite_vincular_a_uma_existente(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        $this->actingAs(User::factory()->for($company)->create());
        $path = $this->xlsx([
            'clientes' => [['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal', 'situacao', 'mes_cancelamento'], ['R1', 'P', 'P', 'B', 100, 'Ativo', null]],
            'metricas' => [['cliente_id', 'mes_ref', 'pedidos'], ['R1', '2026-01', 900]],
        ]);

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('ifood.xlsx', file_get_contents($path)))
            ->assertSee('Colunas obrigatórias')->assertSee('Métricas encontradas (1)')->assertSee('Criar métrica nova')->assertSee('Vincular a uma existente')
            ->assertSee('situacao, mes_cancelamento')
            ->assertSet('metricMappings.0.target', (string) $company->metricDefinitions()->value('id')) // "pedidos" já existe: vem vinculada
            ->assertSee('Métrica existente')
            ->set('metricMappings.0.target', 'new')->assertSee('Tipo do valor')->assertSee('Quando piora')
            ->call('usarExistente', 0)->assertSet('metricMappings.0.target', (string) $company->metricDefinitions()->value('id'));
    }

    public function test_segmento_e_plano_sao_opcionais_e_ficam_como_nao_informado(): void
    {
        $company = Company::factory()->create();
        $path = $this->xlsx([
            'dados' => [['cliente_id', 'mes_ref', 'porte', 'valor_mensal', 'pedidos'], ['R1', '2026-01', 'Pequeno', 100, 900], ['R1', '2026-02', 'Pequeno', 100, 800]],
        ]);
        $tabela = PlanilhaReader::lerModelo($path, 'xlsx');
        $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
        $metricas = array_map(fn (array $m): array => ['direction' => 'lower', 'healthy_value' => 800, 'critical_value' => 100, 'weight' => 20, 'description' => 'Pedidos'] + $m, $sugestao['metrics']);

        DynamicImportService::importar($company, $tabela, $sugestao['structure'], $metricas);

        app(CompanyContext::class)->within($company, function (): void {
            $cliente = Customer::firstOrFail();
            $this->assertSame('Não informado', $cliente->segment);
            $this->assertSame('Não informado', $cliente->plan);
        });

        // porte continua obrigatório
        $semPorte = ['cliente_id' => 'cliente_id', 'mes_ref' => 'mes_ref', 'segmento' => null, 'porte' => null, 'plano' => null, 'valor_mensal' => 'valor_mensal'];
        $this->expectException(InvalidArgumentException::class);
        DynamicImportService::importar($company, $tabela, $semPorte, $metricas);
    }

    public function test_relatorio_de_evidencias_e_gerado_para_empresa_so_com_metricas_proprias_com_ou_sem_cancelamentos(): void
    {
        foreach ([true, false] as $comCancelados) {
            $company = Company::factory()->create();
            $clientes = [['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal', 'situacao', 'mes_cancelamento']];
            $mensal = [['cliente_id', 'mes_ref', 'pedidos']];
            foreach (range(1, 6) as $i) {
                $cancelou = $comCancelados && $i <= 3;
                $clientes[] = ["C{$i}", 'Pizzaria', 'Pequeno', 'Basico', 100, $cancelou ? 'Cancelado' : 'Ativo', $cancelou ? '2026-04' : null];
                foreach (['2026-01', '2026-02', '2026-03'] as $mes) {
                    $mensal[] = ["C{$i}", $mes, $cancelou ? 200 : 900];
                }
            }
            $tabela = PlanilhaReader::lerModelo($this->xlsx(['clientes' => $clientes, 'metricas' => $mensal]), 'xlsx');
            $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
            DynamicImportService::importar($company, $tabela, $sugestao['structure'], array_map(fn (array $m): array => ['direction' => 'lower', 'healthy_value' => 800, 'critical_value' => 100, 'weight' => 20, 'description' => 'Pedidos'] + $m, $sugestao['metrics']));

            $pdf = app(CompanyContext::class)->within($company, fn (): string => RelatorioService::carteira($company));
            $this->assertStringStartsWith('%PDF', $pdf);
        }
    }

    public function test_painel_tem_abas_de_visao_geral_e_graficos_e_o_botao_do_relatorio_em_qualquer_empresa(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);
        TemplateImportService::importar($company, ['cabecalhos' => TemplateLayout::headers($company), 'linhas' => [$this->row('A', '2026-06', '20')]]);
        $this->actingAs(User::factory()->for($company)->create());

        $this->get('/painel')->assertOk()->assertSee('Visão geral')->assertSee('Gráficos')->assertSee('Gerar relatório de evidências');
    }

    public function test_abas_de_dados_sem_cliente_id_ou_com_coluna_repetida_sao_recusadas(): void
    {
        $semId = $this->xlsx(['a' => [['cliente_id', 'mes_ref', 'x'], ['C1', '2026-01', 1]], 'b' => [['nome', 'y'], ['C1', 2]]]);
        try {
            PlanilhaReader::lerModelo($semId, 'xlsx');
            $this->fail('Aceitou aba sem cliente_id.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cliente_id', $e->getMessage());
        }

        $repetida = $this->xlsx(['a' => [['cliente_id', 'mes_ref', 'x'], ['C1', '2026-01', 1]], 'b' => [['cliente_id', 'x'], ['C1', 2]]]);
        $this->expectExceptionMessage('aparece nas abas');
        PlanilhaReader::lerModelo($repetida, 'xlsx');
    }

    public function test_dicionario_preenchido_configura_a_metrica_inteira_e_importa_com_varias_abas(): void
    {
        $company = Company::factory()->create();
        $path = $this->xlsx([
            'Leia-me' => [['Instruções']],
            'dicionario' => [
                ['aba', 'campo', 'tipo', 'descricao', 'piora_quando', 'valor_saudavel', 'valor_critico', 'peso'],
                ['metricas', 'uso', 'Percentual', 'Uso da plataforma no mês.', 'diminui', 80, 30, 25],
            ],
            'clientes' => [['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal'], ['C1', 'Varejo', 'Grande', 'Pro', 1500]],
            'metricas' => [['cliente_id', 'mes_ref', 'uso'], ['C1', '2026-01', 40], ['C1', '2026-02', 35]],
        ]);

        $tabela = PlanilhaReader::lerModelo($path, 'xlsx');
        $this->assertCount(2, $tabela['linhas']);
        $this->assertSame('Varejo', $tabela['linhas'][1]['segmento']);

        $sugestao = DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
        $this->assertEqualsCanonicalizing(
            ['value_type' => 'percentage', 'description' => 'Uso da plataforma no mês.', 'direction' => 'lower', 'healthy_value' => 80.0, 'critical_value' => 30.0, 'weight' => 25.0],
            array_intersect_key($sugestao['metrics'][0], array_flip(['value_type', 'description', 'direction', 'healthy_value', 'critical_value', 'weight'])),
        );

        $resultado = DynamicImportService::importar($company, $tabela, $sugestao['structure'], $sugestao['metrics']);
        $this->assertSame(1, $resultado['clientes']);
        $this->assertSame(2, $resultado['valores_metricas']);
    }

    public function test_modelo_xlsx_baixado_tem_leia_me_dicionario_e_abas_de_dados(): void
    {
        $company = Company::factory()->create();
        $this->definition($company);

        $caminho = DynamicImportService::xlsxModelo($company);
        $abas = [];
        $reader = new Reader;
        $reader->open($caminho);
        foreach ($reader->getSheetIterator() as $sheet) {
            $abas[] = $sheet->getName();
        }
        $reader->close();
        $tabela = PlanilhaReader::lerModelo($caminho, 'xlsx');
        @unlink($caminho);

        $this->assertSame(['Leia-me', 'dicionario', 'clientes', 'metricas_mensais'], $abas);
        $this->assertContains('cliente_id', $tabela['cabecalhos']);
        $this->assertNotEmpty($tabela['dicionario']);
        $this->actingAs(User::factory()->for($company)->create())->get(route('planilha.modelo.xlsx'))->assertOk()->assertDownload('seer-modelo-'.$company->slug.'.xlsx');
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
