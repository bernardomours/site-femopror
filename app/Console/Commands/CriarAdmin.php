<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CriarAdmin extends Command
{
    protected $signature = 'femopror:criar-admin';

    protected $description = 'Cria (ou promove) um administrador do painel da diretoria';

    /**
     * A senha é pedida interativamente de propósito: como argumento de linha de
     * comando ela ficaria no ~/.bash_history do servidor e apareceria no `ps`.
     */
    public function handle(): int
    {
        $nome = text(
            label: 'Nome do administrador',
            required: true,
            validate: fn (string $valor) => mb_strlen(trim($valor)) < 3
                ? 'Informe o nome completo.'
                : null,
        );

        $email = text(
            label: 'E-mail de acesso',
            required: true,
            validate: fn (string $valor) => Validator::make(
                ['email' => $valor],
                ['email' => ['required', 'email']],
            )->fails() ? 'Informe um e-mail válido.' : null,
        );

        $email = mb_strtolower(trim($email));
        $existente = User::where('email', $email)->first();

        if ($existente && ! $this->confirm("Já existe um usuário com {$email}. Deseja promovê-lo a administrador e trocar a senha?")) {
            $this->warn('Operação cancelada.');

            return self::FAILURE;
        }

        $senha = password(
            label: 'Senha (mínimo 12 caracteres)',
            required: true,
            validate: fn (string $valor) => mb_strlen($valor) < 12
                ? 'A senha precisa de pelo menos 12 caracteres.'
                : null,
        );

        if ($senha !== password(label: 'Confirme a senha', required: true)) {
            $this->error('As senhas não coincidem.');

            return self::FAILURE;
        }

        $user = $existente ?? new User;
        $user->name = trim($nome);
        $user->email = $email;
        $user->password = Hash::make($senha);
        $user->is_admin = true;
        $user->church_id = null;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info(($existente ? 'Usuário promovido' : 'Administrador criado').": {$email}");
        $this->line('Acesse o painel em '.url('/area-da-diretoria').'.');

        return self::SUCCESS;
    }
}
