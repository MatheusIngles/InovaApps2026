<?php

namespace Tests\Feature;

use App\Filament\Pages\Configuracoes;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\User;
use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracaoEmpresaTest extends TestCase
{
    use RefreshDatabase;

    /** Cliente com uso baixo e SLA perfeito: o score depende só do peso dado ao uso. */
    private function empresaComCliente(): Company
    {
        $company = Company::factory()->create();
        app(CompanyContext::class)->within($company, function () use ($company) {
            $c = Customer::factory()->create(['company_id' => $company->id]);
            foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $mes) {
                CustomerMetric::factory()->create(['customer_id' => $c->id, 'reference_month' => $mes, 'platform_usage_percentage' => 30, 'sla_percentage' => 100,
                    'tickets_opened' => 0, 'tickets_reopened' => 0, 'formal_complaints' => 0, 'payment_delay_days' => 0, 'meetings_expected' => 1, 'meetings_completed' => 1]);
            }
        });

        return $company;
    }

    private function dados(Company $company, array $pesos): array
    {
        $d = CompanyConfig::ler($company);
        $d['metricas'] = array_map(fn ($m) => ['k' => $m['k'], 'peso' => $pesos[$m['k']] ?? 0], $d['metricas']);

        return $d;
    }

    public function test_pesos_da_empresa_mudam_o_score_e_disparam_recalculo(): void
    {
        $company = $this->empresaComCliente();
        $score = fn () => app(CompanyContext::class)->within($company, fn () => Customer::dashboard()->first()->score);

        $this->assertTrue(CompanyConfig::salvar($company, $this->dados($company, ['uso' => 100])));
        $this->assertSame(100, $score());

        $this->assertTrue(CompanyConfig::salvar($company, $this->dados($company, ['sla' => 100])));
        $this->assertSame(0, $score()); // SLA perfeito
        $this->assertFalse(CompanyConfig::salvar($company, $this->dados($company, ['sla' => 100]))); // nada mudou: sem recálculo
    }

    public function test_reordenar_metricas_na_tela_altera_score_e_exposicao_do_cliente(): void
    {
        $company = $this->empresaComCliente();
        $this->actingAs(User::factory()->for($company)->create());
        $avaliacao = fn () => app(CompanyContext::class)->within($company, fn () => Customer::dashboard()->first());

        Livewire::test(Configuracoes::class)->call('salvar')->assertHasNoErrors();
        $this->assertSame(20, $avaliacao()->score);

        $metricas = CompanyConfig::ler($company->fresh())['metricas'];
        $uso = collect($metricas)->firstWhere('k', 'uso');
        $reordenadas = collect($metricas)->reject(fn ($metrica) => $metrica['k'] === 'uso')->push($uso)->values()->all();

        Livewire::test(Configuracoes::class)->set('data.metricas', $reordenadas)->call('salvar')->assertHasNoErrors();

        $this->assertSame('uso', $company->fresh()->metric_weights[7]['k']);
        $this->assertSame(8, $avaliacao()->score);
        $this->assertEqualsWithDelta($avaliacao()->valor * 0.08, $avaliacao()->exposicao, 0.01);

        $metricasSemUso = collect(CompanyConfig::ler($company->fresh())['metricas'])
            ->map(fn ($metrica) => $metrica['k'] === 'uso' ? [...$metrica, 'ativa' => false] : $metrica)
            ->all();

        Livewire::test(Configuracoes::class)->set('data.metricas', $metricasSemUso)->call('salvar')->assertHasNoErrors();

        $this->assertSame(0.0, $company->fresh()->pesos()['uso']);
        $this->assertSame(0, $avaliacao()->score);
        $this->assertSame(0.0, $avaliacao()->exposicao);
    }

    public function test_score_normaliza_pesos_e_soma_zero_e_rejeitada(): void
    {
        $historico = array_fill(0, 3, ['uso_plataforma_pct' => 30, 'pct_sla_cumprido' => 100, 'chamados_abertos' => 0, 'chamados_reabertos' => 0,
            'reclamacoes_formais' => 0, 'dias_atraso_pagamento' => 0, 'reunioes_previstas' => 1, 'reunioes_realizadas' => 1]);

        // pesos que não somam 100 dão o mesmo resultado que os proporcionais
        $this->assertSame(Risco::calcular($historico, [], ['uso' => 20, 'sla' => 20])['score'], Risco::calcular($historico, [], ['uso' => 1, 'sla' => 1])['score']);
        $this->assertSame(0, Risco::calcular($historico, [], ['uso' => 0, 'sla' => 0])['score']); // sem divisão por zero

        $company = Company::factory()->create();
        $this->expectException(ValidationException::class);
        CompanyConfig::salvar($company, $this->dados($company, []));
    }

    public function test_limiares_precisam_ser_decrescentes_e_definem_o_nivel(): void
    {
        $company = Company::factory()->create();
        $d = CompanyConfig::ler($company);
        $d['limiares'] = ['critico' => 50, 'alto' => 60, 'medio' => 10];

        try {
            CompanyConfig::salvar($company, $d);
            $this->fail('Limiares inválidos deveriam falhar.');
        } catch (ValidationException) {
        }

        $this->assertSame('Alto', Risco::nivel(60, ['critico' => 70, 'alto' => 60, 'medio' => 10]));
        $this->assertSame('Crítico', Risco::nivel(60, ['critico' => 60, 'alto' => 40, 'medio' => 10]));
    }

    public function test_tema_valida_cor_e_restaurar_preserva_configuracao_operacional_do_chat(): void
    {
        $chat = ['enabled' => false, 'ollama_model' => 'llama3.2:3b', 'instrucoes' => 'Tom formal.'];
        $company = Company::factory()->create(['chat_settings' => $chat]);
        $d = CompanyConfig::ler($company);
        $d['tema']['primary'] = 'azul';

        try {
            CompanyConfig::salvar($company, $d);
            $this->fail('Cor inválida deveria falhar.');
        } catch (ValidationException) {
        }

        $d['tema'] = ['primary' => '#0d9488', 'secondary' => '#115e59', 'font' => 'Inter', 'logo' => null];
        $d['chat'] = ['enabled' => true, 'ollama_model' => 'outro-modelo'];
        CompanyConfig::salvar($company, $d);
        $this->assertSame('#0d9488', $company->fresh()->tema()['primary']);
        $this->assertSame($chat, $company->fresh()->chat_settings);

        CompanyConfig::restaurar($company);
        $this->assertSame(Company::TEMA_PADRAO['primary'], $company->fresh()->tema()['primary']);
        $this->assertSame($chat, $company->fresh()->chat_settings);
    }

    public function test_tela_de_configuracoes_salva_e_o_tema_da_empresa_vai_para_a_pagina(): void
    {
        $company = Company::factory()->create(['theme' => ['primary' => '#0d9488', 'secondary' => '#115e59', 'font' => 'Inter']]);
        $this->actingAs(User::factory()->for($company)->create());
        app(CompanyContext::class)->within($company, fn () => Customer::factory()->create(['company_id' => $company->id])); // já fez a carga inicial

        $this->get('/configuracoes')->assertOk()->assertSee('Prioridade das métricas')->assertSee('Acrescentar novos meses')->assertSee('#115e59', false)->assertSee('Inter')
            ->assertDontSee('Chat com IA')->assertDontSee('Modelo local (Ollama)');
        Livewire::test(Configuracoes::class)->set('data.limiares.critico', 70)->call('salvar')->assertHasNoErrors();
        $this->assertSame(70, $company->fresh()->limiares()['critico']);
    }

    public function test_configuracao_de_uma_empresa_nao_afeta_outra(): void
    {
        [$a, $b] = [Company::factory()->create(), Company::factory()->create()];
        CompanyConfig::salvar($a, $this->dados($a, ['uso' => 50, 'sla' => 50]));

        $this->assertNull($b->fresh()->metric_weights);
        $this->assertSame(Risco::PESOS['uso'], $b->fresh()->pesos()['uso']);
    }

    public function test_cor_primaria_clara_demais_e_recusada(): void
    {
        $company = Company::factory()->create();
        $d = CompanyConfig::ler($company);
        $d['tema']['primary'] = '#fde68a'; // amarelo claro: texto branco dos botões não seria legível

        $this->assertLessThan(3, Tema::contrasteComBranco('#fde68a'));
        $this->assertGreaterThan(3, Tema::contrasteComBranco('#2563eb'));
        $this->expectException(ValidationException::class);
        CompanyConfig::salvar($company, $d);
    }

    public function test_cor_do_logo_ignora_o_branco_e_pega_o_vermelho(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Extensão GD não ativa.');
        }

        $img = imagecreatetruecolor(100, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 234, 29, 44)); // vermelho
        imagefilledrectangle($img, 20, 30, 80, 60, imagecolorallocate($img, 255, 255, 255)); // "texto" branco
        $arquivo = tempnam(sys_get_temp_dir(), 'logo');
        imagepng($img, $arquivo);

        [$r, $g] = sscanf(Tema::corDoLogo($arquivo), '#%02x%02x');
        $this->assertTrue($r > 200 && $g < 60, 'esperava o vermelho, não o branco');

        imagefill($img, 0, 0, imagecolorallocate($img, 128, 128, 128)); // logo só em cinza: mantém o padrão
        imagepng($img, $arquivo);
        $this->assertNull(Tema::corDoLogo($arquivo));
    }
}
