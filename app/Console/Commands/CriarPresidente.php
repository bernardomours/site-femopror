<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

/**
 * Cria (ou promove) o presidente de uma UMP local — quem entra no `/ump`.
 *
 * Existe pelo mesmo motivo do `femopror:criar-admin`: `is_church_president` e
 * `church_id` decidem acesso a painel e **não são mass assignable** (ver o
 * `#[Fillable]` do `User`), então não há caminho pelo cadastro público nem pelo
 * perfil. Antes disso, o único jeito era um one-liner de `tinker` — que é fácil
 * de errar, não valida nada e deixa a senha no histórico do shell.
 */
class CriarPresidente extends Command
{
    protected $signature = 'femopror:criar-presidente';

    protected $description = 'Cria (ou promove) o presidente de uma UMP local, com acesso ao painel /ump';

    public function handle(): int
    {
        if (Church::query()->doesntExist()) {
            $this->error('Nenhuma igreja cadastrada. Cadastre a igreja antes, no painel da diretoria.');

            return self::FAILURE;
        }

        $nome = text(
            label: 'Nome do presidente',
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

        if ($existente?->is_admin) {
            // Admin tem `church_id` nulo de propósito: ele enxerga todas as
            // igrejas. Amarrá-lo a uma UMP mudaria o escopo dele em silêncio.
            $this->error("{$email} é administrador da federação. Use outra conta para o presidente da UMP.");

            return self::FAILURE;
        }

        if ($existente && ! $this->confirm("Já existe um usuário com {$email}. Deseja torná-lo presidente e trocar a senha?")) {
            $this->warn('Operação cancelada.');

            return self::FAILURE;
        }

        $igrejaId = search(
            label: 'De qual UMP ele é presidente?',
            options: fn (string $busca) => Church::query()
                ->when($busca !== '', fn ($q) => $q->where('name', 'like', "%{$busca}%"))
                ->orderBy('name')
                ->limit(20)
                ->pluck('name', 'id')
                ->all(),
        );

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

        /*
         * Atribuição direta, não `fill()`: `is_church_president` e `church_id`
         * ficam fora do mass assignment justamente para não entrarem por aí.
         * E `password` é NOT NULL sem default — criar o usuário em duas etapas
         * (criar e depois completar) morre no banco.
         */
        $user = $existente ?? new User;
        $user->name = trim($nome);
        $user->email = $email;
        $user->password = Hash::make($senha);
        $user->church_id = (int) $igrejaId;
        $user->is_church_president = true;
        $user->email_verified_at ??= now();
        $user->save();

        $igreja = Church::find($igrejaId);

        $this->info(($existente ? 'Usuário promovido' : 'Presidente criado').": {$email}");
        $this->line("UMP: {$igreja?->name}");
        $this->line('Acesse o painel em '.url('/ump').'.');

        return self::SUCCESS;
    }
}
