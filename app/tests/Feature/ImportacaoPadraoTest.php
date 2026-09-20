<?php

namespace Tests\Feature;

use App\Livewire\ImportarPlanilha;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportacaoPadraoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: int|null, 1: string, 2: string}> código => [atenção, nível, situação] */
    private function resultado(Company $company): array
    {
        return app(CompanyContext::class)->within($company, fn () => Customer::dashboard()->get()
            ->mapWithKeys(fn ($c): array => [$c->codigo => [$c->score, $c->nivel, $c->status]])->all());
    }

    public function test_planilha_no_formato_padrao_enviada_pela_tela_gera_o_mesmo_resultado_do_seed(): void
    {
        $xlsx = base_path('../dados/INOVAAPPS_base_de_dados.xlsx');

        // o que o seeder faz
        $seed = Company::factory()->create();
        $tabela = PlanilhaReader::ler($xlsx, 'xlsx');
        ImportService::importar($seed, $tabela['linhas'], ImportService::sugerirMapeamento($tabela['cabecalhos']));

        // empresa nova enviando a mesma planilha pela tela
        $nova = Company::factory()->create();
        $this->actingAs(User::factory()->for($nova)->create());
        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('base.xlsx', file_get_contents($xlsx)))
            ->assertSet('formatoPadrao', true)
            ->assertSee('Planilha reconhecida')
            ->call('importar')
            ->assertRedirect('/');

        $esperado = $this->resultado($seed);
        $this->assertCount(80, $esperado);
        $this->assertSame($esperado, $this->resultado($nova));
        $this->assertTrue($nova->fresh()->hasLegacyMetrics());
        $this->assertSame(22, collect($esperado)->where(2, 'Cancelado')->count());
    }

    public function test_empresa_com_metricas_proprias_nao_e_desviada_para_o_importador_dos_sinais_padrao(): void
    {
        $company = Company::factory()->create();
        $company->metricDefinitions()->create(['code' => 'pedidos', 'label' => 'Pedidos', 'description' => 'x', 'value_type' => 'decimal', 'direction' => 'higher',
            'healthy_value' => 0, 'critical_value' => 10, 'weight' => 10, 'enabled' => true]);
        app(CompanyContext::class)->within($company, fn () => Customer::factory()->create(['company_id' => $company->id]));
        $this->actingAs(User::factory()->for($company)->create());

        Livewire::test(ImportarPlanilha::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('base.xlsx', file_get_contents(base_path('../dados/INOVAAPPS_base_de_dados.xlsx'))))
            ->assertSet('formatoPadrao', false);
    }
}
