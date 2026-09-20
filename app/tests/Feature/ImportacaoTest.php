<?php

namespace Tests\Feature;

use App\Jobs\ImportarPlanilhaJob;
use App\Livewire\ImportarPlanilha;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\CustomerNps;
use App\Models\User;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ImportacaoTest extends TestCase
{
    use RefreshDatabase;

    private function importar(Company $company, string $csv, ?array $mapa = null): array
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($arquivo, $csv);
        $tabela = PlanilhaReader::ler($arquivo, 'csv');

        return ImportService::importar($company, $tabela['linhas'], $mapa ?? ImportService::sugerirMapeamento($tabela['cabecalhos']));
    }

    public function test_csv_com_cabecalhos_livres_separador_e_decimal_brasileiros(): void
    {
        $company = Company::factory()->create();
        $csv = "Codigo;Mes;Valor;Uso;SLA%;NPS;Status\nX1;2026-01;R\$ 1.234,50;80,5;90;;Ativo\nX1;01/2026;1000;70;;9;Ativo\nX1;2026-02;1234,5;60;;7;Ativo\n";

        $stats = $this->importar($company, $csv);

        $this->assertSame(['clientes' => 1, 'meses' => 2, 'nps' => 2, 'ignoradas' => 0], $stats); // 01/2026 e 2026-01 são o mesmo mês
        app(CompanyContext::class)->within($company, function () {
            $this->assertSame('1234.50', Customer::first()->monthly_value);
            $this->assertNull(CustomerMetric::where('reference_month', '2026-02-01')->first()->sla_percentage); // vazio = sem chamados
        });
    }

    public function test_reimportar_nao_duplica_e_campos_ausentes_ficam_neutros(): void
    {
        $company = Company::factory()->create();
        $csv = "cliente_id,mes_ref,chamados_abertos\nA,2026-03,4\n";

        $this->importar($company, $csv);
        $this->importar($company, $csv);

        app(CompanyContext::class)->within($company, function () {
            $this->assertSame(1, Customer::count());
            $this->assertSame(1, CustomerMetric::count());
            $this->assertSame('100.00', CustomerMetric::first()->platform_usage_percentage); // sem coluna de uso: não penaliza
            $this->assertSame(0, CustomerNps::count());
        });
    }

    public function test_linhas_invalidas_sao_contadas_e_codigo_obrigatorio(): void
    {
        $company = Company::factory()->create();

        $stats = $this->importar($company, "cliente_id,mes_ref,chamados_abertos\nA,2026-13,1\n,2026-01,2\nB,2026-02,3\n");
        $this->assertSame(['clientes' => 2, 'meses' => 1, 'nps' => 0, 'ignoradas' => 2], $stats);

        $this->expectException(InvalidArgumentException::class);
        ImportService::importar($company, [['x' => 1]], ['cliente_id' => null]);
    }

    public function test_cancelado_sem_mes_de_saida_e_cliente_sem_metricas(): void
    {
        $company = Company::factory()->create();

        $this->importar($company, "cliente_id,situacao\nA,Cancelado\nB,Ativo\n");

        app(CompanyContext::class)->within($company, function () {
            $this->assertNull(Customer::firstWhere('external_code', 'A')->cancelled_at);
            $this->assertSame(2, Customer::count());
            $this->assertSame(0, Customer::firstWhere('external_code', 'B')->score); // sem métricas, sem avaliação
        });

        // cancelado com métricas mas sem mês de saída: assume o mês seguinte ao último e o recálculo não quebra
        $this->importar($company, 'cliente_id,mes_ref,situacao,chamados_abertos
A,2026-01,Cancelado,1
A,2026-02,Cancelado,2
');
        app(CompanyContext::class)->within($company, fn () => $this->assertSame('2026-03', Customer::firstWhere('external_code', 'A')->mes_cancel));
    }

    public function test_planilha_do_desafio_em_xlsx_e_achatada(): void
    {
        $tabela = PlanilhaReader::ler(database_path('data/INOVAAPPS_base_de_dados.xlsx'), 'xlsx');

        $this->assertContains('cliente_id', $tabela['cabecalhos']);
        $this->assertContains('nota_nps', $tabela['cabecalhos']);
        $this->assertGreaterThanOrEqual(1295, count($tabela['linhas']));
    }

    public function test_tela_de_planilha_importa_metrica_livre_apos_confirmacao(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->for($company)->create());
        $headers = ['cliente_id', 'mes_ref', 'segmento', 'porte', 'plano', 'valor_mensal', 'uso_plataforma_pct'];
        $row = [
            'cliente_id' => 'A', 'mes_ref' => '2026-01', 'segmento' => 'Varejo', 'porte' => 'Pequeno',
            'plano' => 'Básico', 'valor_mensal' => '1000', 'uso_plataforma_pct' => '70',
        ];
        $content = implode(';', $headers)."\n".implode(';', array_map(fn ($header) => $row[$header] ?? '', $headers))."\n";
        $csv = UploadedFile::fake()->createWithContent('carteira.csv', $content);

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', $csv)
            ->assertSet('cabecalhos', $headers)
            ->set('metricMappings.0.description', 'Percentual mensal de uso da plataforma')
            ->set('metricMappings.0.direction', 'lower')
            ->set('metricMappings.0.healthy_value', 100)
            ->set('metricMappings.0.critical_value', 0)
            ->call('importar')
            ->assertRedirect('/'); // primeira carga: segue para a tela da empresa

        app(CompanyContext::class)->within($company, function () use ($company) {
            $this->assertSame(1, Customer::count());
            $this->assertSame(1, Customer::where('status', 'Ativo')->count());
            $this->assertSame(0, ChatMessage::count());
            $this->assertNotNull($company->fresh()->imported_at);
            $this->assertSame('cliente_id', $company->fresh()->column_mapping['cliente_id']);
        });
    }

    public function test_arquivo_invalido_e_recusado(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportarPlanilha::class)->set('arquivo', UploadedFile::fake()->create('x.pdf', 10))->assertHasErrors('arquivo');
    }

    public function test_planilha_grande_vai_para_a_fila_e_o_job_importa_e_notifica(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);
        $headers = ['cliente_id', 'mes_ref', 'segmento', 'porte', 'plano', 'valor_mensal', 'observacao'];
        $obs = str_repeat('x', 900);
        $rows = [];
        for ($i = 1; $i <= 2500; $i++) {
            $row = [
                'cliente_id' => 'C'.$i, 'mes_ref' => '2026-01', 'segmento' => 'Varejo', 'porte' => 'Pequeno',
                'plano' => 'Básico', 'valor_mensal' => '1000', 'observacao' => $obs,
            ];
            $rows[] = implode(';', array_map(fn ($header) => $row[$header] ?? '', $headers));
        }
        $csv = UploadedFile::fake()->createWithContent('grande.csv', implode(';', $headers)."\n".implode("\n", $rows)."\n");

        $c = Livewire::test(ImportarPlanilha::class)->set('arquivo', $csv)
            ->set('metricMappings.0.description', 'Observação textual mensal');
        $caminho = $c->get('caminho');
        $c->call('importar')->assertNoRedirect();

        Queue::assertPushed(ImportarPlanilhaJob::class, fn ($job) => $job->companyId === $company->id && $job->caminho === $caminho && $job->structuralMapping !== null);
        $this->assertCount(0, $user->notifications); // ainda não rodou
    }

    public function test_job_importa_notifica_o_usuario_e_apaga_o_arquivo(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        Storage::put('imports/teste.csv', "cliente_id,mes_ref,chamados_abertos\nA,2026-01,3\n");

        (new ImportarPlanilhaJob($company->id, $user->id, 'imports/teste.csv', 'csv', ['cliente_id' => 'cliente_id', 'mes_ref' => 'mes_ref', 'chamados_abertos' => 'chamados_abertos']))->handle();

        $this->assertSame(1, $company->customers()->count());
        $this->assertSame('Planilha importada', $user->notifications->first()->data['title']);
        Storage::assertMissing('imports/teste.csv');
    }

    public function test_job_com_arquivo_invalido_notifica_o_erro(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        Storage::put('imports/ruim.csv', "x,y\n1,2\n");

        (new ImportarPlanilhaJob($company->id, $user->id, 'imports/ruim.csv', 'csv', ['cliente_id' => null]))->handle();

        $this->assertSame('Importação não concluída', $user->notifications->first()->data['title']);
        Storage::assertMissing('imports/ruim.csv');
    }
}
