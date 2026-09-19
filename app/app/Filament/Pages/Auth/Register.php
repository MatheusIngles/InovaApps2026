<?php

namespace App\Filament\Pages\Auth;

use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\Tema;
use Filament\Actions\Action;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use SensitiveParameter;

/** Cadastro de empresa nova: cria o tenant e o primeiro usuário; em seguida o usuário cai na tela de planilha. */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('empresa')->label('Nome da empresa')->required()->maxLength(120)->autofocus(),
            $this->getNameFormComponent()->label('Seu nome')->autofocus(false),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            FileUpload::make('logo')->label('Logo da empresa (opcional)')->helperText('As cores do painel são tiradas do logo. Sem logo, usamos o azul padrão; dá para mudar depois em Configurações.')->image()->disk('public')->directory('logos')->maxSize(1024),
        ]);
    }

    public function getHeading(): string
    {
        return 'Criar conta';
    }

    public function getRegisterFormAction(): Action
    {
        return parent::getRegisterFormAction()->label('Criar conta');
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $logo = $data['logo'] ?? null;
        $empresa = CompanyConfig::criar($data['empresa']);

        if ($logo) {
            $logo = is_array($logo) ? Arr::first($logo) : $logo;
            $cores = Tema::coresDoLogo(Storage::disk('public')->path($logo));
            $empresa->update(['theme' => ['logo' => $logo] + ($cores ? ['primary' => $cores[0], 'secondary' => $cores[1]] : [])]);
        }

        return $empresa->users()->create(Arr::only($data, ['name', 'email', 'password']));
    }
}
