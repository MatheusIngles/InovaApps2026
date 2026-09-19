<?php

namespace Tests\Feature;

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

    public function test_tela_de_planilha_envia_mapeia_e_importa(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->for($company)->create());
        $csv = UploadedFile::fake()->createWithContent('carteira.csv', file_get_contents(database_path('data/exemplo_planilha.csv')));

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', $csv)
            ->assertSet('data.mapa.cliente_id', 'Codigo')
            ->assertSet('data.mapa.uso_plataforma_pct', 'Uso')
            ->call('importar')
            ->assertRedirect('/'); // primeira carga: segue para a tela da empresa

        app(CompanyContext::class)->within($company, function () use ($company) {
            $this->assertSame(6, Customer::count());
            $this->assertSame(5, Customer::where('status', 'Ativo')->count());
            $this->assertSame(0, ChatMessage::count());
            $this->assertNotNull($company->fresh()->imported_at);
            $this->assertSame('Codigo', $company->fresh()->column_mapping['cliente_id']);
        });
    }

    public function test_arquivo_invalido_e_recusado(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportarPlanilha::class)->set('arquivo', UploadedFile::fake()->create('x.pdf', 10))->assertHasErrors('arquivo');
    }
}
