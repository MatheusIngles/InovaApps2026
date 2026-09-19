<?php

namespace App\Support\Notificacoes;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Support\Risco;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Notificações proativas para os usuários da empresa (tenant): risco subindo de nível entre dois
 * meses reais e baixo contato (poucas reuniões realizadas) com um cliente. Disparadas a cada
 * recálculo de risco, com os dados que já existem — sem infraestrutura nova de agendamento.
 */
class NotificacaoService
{
    private const ORDEM_NIVEL = ['Baixo' => 0, 'Médio' => 1, 'Alto' => 2, 'Crítico' => 3];

    /**
     * @param  array{score: int, nivel: string, reference_month: Carbon}  $resultado
     * @param  array{critico: int, alto: int, medio: int}  $limiares
     */
    public static function avaliar(Company $company, Customer $cliente, ?RiskAssessment $anterior, array $resultado, array $limiares): void
    {
        if ($cliente->cancelada()) {
            return; // não há ação a tomar com quem já cancelou
        }

        $usuarios = $company->users;

        if ($usuarios->isEmpty()) {
            return;
        }

        self::escalonamento($usuarios, $cliente, $anterior, $resultado, $limiares);
        self::baixoContato($usuarios, $cliente, $resultado);
    }

    /** Sobe de nível entre o mês anterior real e o mês recém-calculado (ignora recálculo do mesmo mês por mudança de peso). */
    private static function escalonamento(Collection $usuarios, Customer $cliente, ?RiskAssessment $anterior, array $resultado, array $limiares): void
    {
        if (! $anterior || $anterior->reference_month->gte($resultado['reference_month'])) {
            return;
        }

        $nivelAntes = Risco::nivel((int) $anterior->health_score, $limiares);
        $nivelDepois = $resultado['nivel'];

        if (self::ORDEM_NIVEL[$nivelDepois] <= self::ORDEM_NIVEL[$nivelAntes]) {
            return; // manteve ou melhorou
        }

        $chave = "risco-subiu:{$cliente->id}:{$resultado['reference_month']->toDateString()}";
        $critico = $nivelDepois === 'Crítico';

        self::enviar($usuarios, $chave,
            Notification::make()
                ->title("{$cliente->displayName()} está subindo de nível de risco")
                ->body("Foi de {$nivelAntes} para {$nivelDepois} este mês (score {$resultado['score']}/100)."
                    .($critico
                        ? ' Chegou ao ponto mais alto de atenção — vale priorizar um contato ainda hoje.'
                        : ' Antes que continue subindo, um contato próximo agora pode reverter o quadro.'))
                ->icon('heroicon-o-arrow-trending-up')
                ->color($critico ? 'danger' : 'warning')
                ->viewData(['chave' => $chave])
                ->actions([
                    Action::make('ver')->label('Ver empresa')->url(EmpresaResource::getUrl('view', ['record' => $cliente]))->markAsRead(),
                ]),
        );
    }

    /** Poucas (ou nenhuma) reunião realizada no mês mais recente, frente ao previsto. */
    private static function baixoContato(Collection $usuarios, Customer $cliente, array $resultado): void
    {
        // whereDate (não where): a coluna guarda o mês ora como data pura, ora com hora, a depender de como a linha foi gravada.
        $metrica = $cliente->metrics()->whereDate('reference_month', $resultado['reference_month'])->first();

        if (! $metrica || $metrica->meetings_expected <= 0) {
            return; // sem cadência de reuniões prevista, não há o que cobrar
        }

        $realizadas = $metrica->meetings_completed;
        $previstas = $metrica->meetings_expected;

        if ($previstas > 0 && $realizadas / $previstas >= 0.5) {
            return; // manteve pelo menos metade da cadência combinada
        }

        $chave = "baixo-contato:{$cliente->id}:{$resultado['reference_month']->toDateString()}";
        $mensagem = $realizadas === 0
            ? "Nenhuma reunião aconteceu com {$cliente->displayName()} este mês, de {$previstas} prevista(s)."
            : "Só {$realizadas} de {$previstas} reuniões combinadas aconteceram com {$cliente->displayName()} este mês.";

        self::enviar($usuarios, $chave,
            Notification::make()
                ->title("Hora de reaproximar de {$cliente->displayName()}")
                ->body("{$mensagem} Um contato agora ajuda a manter o relacionamento aquecido antes que vire um problema maior.")
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->viewData(['chave' => $chave])
                ->actions([
                    Action::make('ver')->label('Ver empresa')->url(EmpresaResource::getUrl('view', ['record' => $cliente]))->markAsRead(),
                ]),
        );
    }

    /**
     * Envia para cada usuário da empresa, uma única vez por chave (evita repetir o mesmo aviso a cada
     * recálculo). Síncrono (sendNow) mesmo a notificação de banco do Filament sendo ShouldQueue: sem
     * fila, a checagem de duplicidade não corre risco de corrida entre dois recálculos seguidos.
     */
    private static function enviar(Collection $usuarios, string $chave, Notification $notificacao): void
    {
        foreach ($usuarios as $usuario) {
            if (self::jaNotificado($usuario, $chave)) {
                continue;
            }

            NotificationFacade::sendNow($usuario, $notificacao->toDatabase());
        }
    }

    private static function jaNotificado(User $usuario, string $chave): bool
    {
        return $usuario->notifications()->latest()->limit(500)->get()
            ->contains(fn ($n) => ($n->data['viewData']['chave'] ?? null) === $chave);
    }
}
