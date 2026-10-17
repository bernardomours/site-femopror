<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O comando que cria o presidente de UMP.
 *
 * Ele existe porque `is_church_president` e `church_id` não são mass assignable
 * e não têm caminho pelo cadastro público: sem comando, o único jeito era um
 * one-liner de `tinker`.
 */
class CriarPresidenteTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'uma-senha-bem-longa';

    public function test_cria_o_presidente_com_acesso_ao_painel_da_ump(): void
    {
        $igreja = Church::factory()->create(['name' => 'IP Central de Mossoró']);

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Ana Presidente')
            ->expectsQuestion('E-mail de acesso', 'ANA@Exemplo.test')
            ->expectsSearch(
                'De qual UMP ele é presidente?',
                search: '',
                answer: $igreja->id,
                answers: [$igreja->id => $igreja->name],
            )
            ->expectsQuestion('Senha (mínimo 12 caracteres)', self::SENHA)
            ->expectsQuestion('Confirme a senha', self::SENHA)
            ->assertSuccessful();

        $user = User::sole();

        // E-mail normalizado: digitar com maiúscula não pode criar uma segunda conta.
        $this->assertSame('ana@exemplo.test', $user->email);
        $this->assertSame($igreja->id, $user->church_id);
        $this->assertTrue($user->is_church_president);
        $this->assertFalse((bool) $user->is_admin);

        // O que o comando existe para garantir: a conta abre o /ump.
        $this->actingAs($user)->get('/ump')->assertSuccessful();
    }

    public function test_senha_e_gravada_com_hash(): void
    {
        $igreja = Church::factory()->create();

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Bruno Presidente')
            ->expectsQuestion('E-mail de acesso', 'bruno@exemplo.test')
            ->expectsSearch('De qual UMP ele é presidente?', search: '', answer: $igreja->id, answers: [$igreja->id => $igreja->name])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', self::SENHA)
            ->expectsQuestion('Confirme a senha', self::SENHA)
            ->assertSuccessful();

        $this->assertNotSame(self::SENHA, User::sole()->password);
        $this->assertTrue(password_verify(self::SENHA, User::sole()->password));
    }

    public function test_senhas_diferentes_nao_criam_conta(): void
    {
        $igreja = Church::factory()->create();

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Carla Presidente')
            ->expectsQuestion('E-mail de acesso', 'carla@exemplo.test')
            ->expectsSearch('De qual UMP ele é presidente?', search: '', answer: $igreja->id, answers: [$igreja->id => $igreja->name])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', self::SENHA)
            ->expectsQuestion('Confirme a senha', 'outra-coisa-qualquer')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_promove_conta_existente_sem_criar_outra(): void
    {
        // O jovem já tem conta no site e vira presidente: não pode virar
        // segundo usuário com o mesmo e-mail.
        $igreja = Church::factory()->create();
        $existente = User::factory()->create(['email' => 'ja@existe.test', 'is_church_president' => false]);

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Daniel Presidente')
            ->expectsQuestion('E-mail de acesso', 'ja@existe.test')
            ->expectsConfirmation('Já existe um usuário com ja@existe.test. Deseja torná-lo presidente e trocar a senha?', 'yes')
            ->expectsSearch('De qual UMP ele é presidente?', search: '', answer: $igreja->id, answers: [$igreja->id => $igreja->name])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', self::SENHA)
            ->expectsQuestion('Confirme a senha', self::SENHA)
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertTrue($existente->fresh()->is_church_president);
    }

    public function test_recusar_a_confirmacao_nao_mexe_na_conta(): void
    {
        Church::factory()->create();
        $existente = User::factory()->create(['email' => 'ja@existe.test', 'is_church_president' => false]);
        $senhaAntes = $existente->password;

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Elisa Presidente')
            ->expectsQuestion('E-mail de acesso', 'ja@existe.test')
            ->expectsConfirmation('Já existe um usuário com ja@existe.test. Deseja torná-lo presidente e trocar a senha?', 'no')
            ->assertFailed();

        $this->assertFalse($existente->fresh()->is_church_president);
        $this->assertSame($senhaAntes, $existente->fresh()->password);
    }

    public function test_admin_da_federacao_nao_vira_presidente_de_uma_ump(): void
    {
        /*
         * Admin tem `church_id` nulo de propósito — é o que faz ele enxergar
         * todas as igrejas. Amarrá-lo a uma UMP reduziria o escopo dele sem
         * aviso, e pior: `canAccessPanel('admin')` continuaria valendo, então
         * ficaria um admin com igreja, estado que o resto do sistema não espera.
         */
        Church::factory()->create();
        $admin = User::factory()->create(['email' => 'admin@femopror.test', 'is_admin' => true]);

        $this->artisan('femopror:criar-presidente')
            ->expectsQuestion('Nome do presidente', 'Tentativa')
            ->expectsQuestion('E-mail de acesso', 'admin@femopror.test')
            ->assertFailed();

        $this->assertNull($admin->fresh()->church_id);
        $this->assertFalse($admin->fresh()->is_church_president);
    }

    public function test_sem_igreja_cadastrada_o_comando_explica_em_vez_de_quebrar(): void
    {
        $this->artisan('femopror:criar-presidente')
            ->expectsOutputToContain('Nenhuma igreja cadastrada')
            ->assertFailed();
    }
}
