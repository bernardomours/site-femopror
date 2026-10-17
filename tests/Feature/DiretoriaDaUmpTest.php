<?php

namespace Tests\Feature;

use App\Filament\Ump\Pages\MinhaDiretoria;
use App\Models\Board;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A diretoria da UMP, preenchida pelo próprio presidente.
 *
 * O que esta tela não pode deixar acontecer é um presidente gravar a diretoria
 * de outra igreja — os nomes vão para a página pública daquela UMP.
 */
class DiretoriaDaUmpTest extends TestCase
{
    use RefreshDatabase;

    private function presidente(?Church $igreja = null): User
    {
        return User::factory()->create([
            'church_id' => ($igreja ?? Church::factory()->create())->id,
            'is_church_president' => true,
        ]);
    }

    public function test_presidente_preenche_a_diretoria_da_propria_igreja(): void
    {
        $igreja = Church::factory()->create();

        Livewire::actingAs($this->presidente($igreja))
            ->test(MinhaDiretoria::class)
            ->fillForm([
                'president_name' => 'Ana Presidente',
                'treasurer_name' => 'Caio Tesoureiro',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $diretoria = Board::sole();

        $this->assertSame($igreja->id, $diretoria->church_id);
        $this->assertSame('Ana Presidente', $diretoria->president_name);
        // Nasce ativa: é ela que a home mostra.
        $this->assertTrue($diretoria->is_active);
    }

    public function test_abre_com_a_diretoria_atual_ja_preenchida(): void
    {
        $igreja = Church::factory()->create();
        Board::factory()->create([
            'church_id' => $igreja->id,
            'is_active' => true,
            'president_name' => 'Quem já estava lá',
        ]);

        Livewire::actingAs($this->presidente($igreja))
            ->test(MinhaDiretoria::class)
            ->assertFormSet(['president_name' => 'Quem já estava lá']);
    }

    public function test_salvar_de_novo_atualiza_em_vez_de_criar_outra(): void
    {
        // Duas ativas na mesma igreja e a home passa a mostrar a que o banco
        // devolver primeiro — normalmente a antiga.
        $igreja = Church::factory()->create();
        Board::factory()->create(['church_id' => $igreja->id, 'is_active' => true]);

        Livewire::actingAs($this->presidente($igreja))
            ->test(MinhaDiretoria::class)
            ->fillForm(['president_name' => 'Nome corrigido'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Board::where('church_id', $igreja->id)->count());
        $this->assertSame('Nome corrigido', Board::sole()->president_name);
    }

    public function test_presidente_sem_presidente_preenchido_recebe_erro_de_validacao(): void
    {
        // Esquecimento do usuário é erro no campo, não tela quebrada.
        Livewire::actingAs($this->presidente())
            ->test(MinhaDiretoria::class)
            ->fillForm(['president_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['president_name' => 'required']);

        $this->assertSame(0, Board::count());
    }

    // ------------------------------------------------------- travas

    public function test_formulario_nao_expoe_church_id_nem_is_active(): void
    {
        /*
         * Estes dois decidem **de quem** é a diretoria e **qual** delas a home
         * mostra. Nenhum dos dois pode virar campo de formulário: é o que diz o
         * padrão de segurança do projeto, e já foi exatamente esse o furo na
         * inscrição de congresso (dois `Hidden` com `default()`, adulteráveis
         * por request forjada).
         *
         * Hoje eles estão protegidos de duas formas: não existem no schema, e
         * `save()` os define a partir de `auth()`. A primeira é o que este
         * teste guarda — `getState()` só devolve o que está no schema, então o
         * dia em que alguém adicionar o campo é o dia em que a trava do
         * `save()` passa a ser a única, e isso precisa ser uma decisão
         * consciente, não um acidente.
         */
        $componente = Livewire::actingAs($this->presidente())
            ->test(MinhaDiretoria::class);

        $componente->assertFormFieldDoesNotExist('church_id');
        $componente->assertFormFieldDoesNotExist('is_active');
        $componente->assertFormFieldExists('president_name');
    }

    public function test_diretoria_de_outra_igreja_nao_e_tocada(): void
    {
        $minhaIgreja = Church::factory()->create();
        $outraIgreja = Church::factory()->create();

        $daOutra = Board::factory()->create([
            'church_id' => $outraIgreja->id,
            'is_active' => true,
            'president_name' => 'Presidente da outra UMP',
        ]);

        Livewire::actingAs($this->presidente($minhaIgreja))
            ->test(MinhaDiretoria::class)
            ->fillForm(['president_name' => 'Presidente da minha UMP'])
            ->call('save');

        $this->assertSame('Presidente da outra UMP', $daOutra->fresh()->president_name);
        // E continua ativa: o `booted()` do Board só desativa dentro da mesma igreja.
        $this->assertTrue($daOutra->fresh()->is_active);
    }

    public function test_usuario_sem_igreja_nao_acessa_a_tela(): void
    {
        // Admin da federação entra no /ump mas não tem igreja: não existe
        // diretoria "dele". O caminho dele é o painel da federação.
        $admin = User::factory()->create(['is_admin' => true, 'church_id' => null]);

        $this->assertFalse(MinhaDiretoria::canAccess());

        $this->actingAs($admin)
            ->get('/ump/minha-diretoria')
            ->assertForbidden();
    }

    public function test_membro_comum_nao_entra_no_painel_da_ump(): void
    {
        $membro = User::factory()->create([
            'church_id' => Church::factory()->create()->id,
            'is_church_president' => false,
        ]);

        $this->actingAs($membro)
            ->get('/ump/minha-diretoria')
            ->assertForbidden();
    }
}
