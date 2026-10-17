<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * As duas colunas de cargo gravadas pelo painel.
 *
 * `is_admin` e `is_church_president` estão **fora do `#[Fillable]`** de
 * propósito: são as que decidem acesso a painel. Só que o Filament grava por
 * `update()`/`create()` — mass assignment — e o Eloquent descarta atributo não
 * fillable **em silêncio**. Marcar "presidente", salvar, ver a notificação de
 * sucesso e nada mudar era o sintoma.
 */
class EdicaoDeUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_marcar_como_presidente_no_painel_persiste(): void
    {
        $igreja = Church::factory()->create();
        $membro = User::factory()->create(['church_id' => $igreja->id, 'is_church_president' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $membro->id])
            ->fillForm([
                'name' => $membro->name,
                'email' => $membro->email,
                'church_id' => $igreja->id,
                'is_church_president' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($membro->fresh()->is_church_president, 'o cargo não foi gravado');
    }

    public function test_marcar_como_presidente_realmente_libera_o_painel_da_ump(): void
    {
        /*
         * O que o cargo existe para fazer. Sem isto, "gravou a coluna" poderia
         * ser verdade sem o acesso funcionar.
         *
         * A verificação é por `canAccessPanel()`, e não por um `get('/ump')`:
         * um `Livewire::test()` de página do Filament deixa o painel `admin`
         * como corrente, e a requisição HTTP seguinte no mesmo teste resolve
         * rota no painel errado ("Route [filament.ump.resources.users.index]
         * not defined"). É contaminação do teste, não bug — `/ump` abre
         * normalmente, e `SegurancaTest` cobre a rota.
         */
        $igreja = Church::factory()->create();
        $membro = User::factory()->create(['church_id' => $igreja->id, 'is_church_president' => false]);
        $painel = \Filament\Facades\Filament::getPanel('ump');

        $this->assertFalse($membro->canAccessPanel($painel));

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $membro->id])
            ->fillForm([
                'name' => $membro->name,
                'email' => $membro->email,
                'church_id' => $igreja->id,
                'is_church_president' => true,
            ])
            ->call('save');

        $this->assertTrue($membro->fresh()->canAccessPanel($painel));
    }

    public function test_desmarcar_presidente_tambem_persiste(): void
    {
        // Um `forceFill` só na marcação deixaria o cargo impossível de tirar.
        $igreja = Church::factory()->create();
        $presidente = User::factory()->create(['church_id' => $igreja->id, 'is_church_president' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $presidente->id])
            ->fillForm([
                'name' => $presidente->name,
                'email' => $presidente->email,
                'church_id' => $igreja->id,
                'is_church_president' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($presidente->fresh()->is_church_president);
    }

    public function test_promover_a_admin_no_painel_persiste(): void
    {
        $membro = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $membro->id])
            ->fillForm([
                'name' => $membro->name,
                'email' => $membro->email,
                'is_admin' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($membro->fresh()->is_admin);
        $this->actingAs($membro->fresh())->get('/area-da-diretoria')->assertSuccessful();
    }

    public function test_criar_usuario_ja_com_cargo_grava_o_cargo(): void
    {
        $igreja = Church::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Nova Presidente',
                'email' => 'nova@exemplo.test',
                'password' => 'uma-senha-bem-longa',
                'password_confirmation' => 'uma-senha-bem-longa',
                'church_id' => $igreja->id,
                'is_church_president' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $criada = User::where('email', 'nova@exemplo.test')->sole();

        $this->assertTrue($criada->is_church_president);
        $this->assertSame($igreja->id, $criada->church_id);
    }

    public function test_salvar_volta_para_a_listagem(): void
    {
        // Convenção do painel: toda tela de Create/Edit volta para o índice ao
        // salvar. As de usuário eram as únicas que ficavam paradas na edição.
        $membro = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $membro->id])
            ->fillForm(['name' => 'Nome Corrigido', 'email' => $membro->email])
            ->call('save')
            ->assertRedirect(\App\Filament\Resources\Users\UserResource::getUrl('index'));
    }

    public function test_criar_usuario_tambem_volta_para_a_listagem(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Alguém Novo',
                'email' => 'alguem@exemplo.test',
                'password' => 'uma-senha-bem-longa',
                'password_confirmation' => 'uma-senha-bem-longa',
            ])
            ->call('create')
            ->assertRedirect(\App\Filament\Resources\Users\UserResource::getUrl('index'));
    }

    // ------------------------------------------- as travas que já existiam

    public function test_cargo_continua_fora_do_mass_assignment(): void
    {
        /*
         * A correção é no Resource, não no `#[Fillable]`. Se alguém "resolver"
         * o problema abrindo o fillable, qualquer `create()`/`update()` do
         * sistema passaria a poder promover alguém a admin — inclusive um
         * caminho que não exige admin autenticado.
         */
        $user = User::factory()->create(['is_admin' => false, 'is_church_president' => false]);

        $user->update(['is_admin' => true, 'is_church_president' => true]);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse($user->fresh()->is_church_president);
    }

    public function test_nao_da_para_remover_o_ultimo_admin_pelo_painel(): void
    {
        // A trava do `UserResource`/`User::booted()` continua valendo depois da
        // mudança na gravação.
        $unico = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($unico)
            ->test(EditUser::class, ['record' => $unico->id])
            ->fillForm([
                'name' => $unico->name,
                'email' => $unico->email,
                'is_admin' => false,
            ])
            ->call('save');

        $this->assertTrue($unico->fresh()->is_admin, 'o último admin foi rebaixado');
    }
}
