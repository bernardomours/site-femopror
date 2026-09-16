<?php

namespace Tests\Feature;

use App\Filament\Ump\Resources\CongressSubscriptions\Pages\CreateCongressSubscription;
use App\Filament\Ump\Resources\CongressSubscriptions\Pages\EditCongressSubscription;
use App\Models\Church;
use App\Models\CongressSubscription;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SegurancaTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- painéis

    public function test_usuario_comum_nao_entra_no_painel_da_diretoria(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/area-da-diretoria')
            ->assertForbidden();
    }

    public function test_presidente_de_ump_entra_no_painel_da_ump(): void
    {
        // canAccessPanel() devolvia is_admin para os DOIS painéis: o /ump ficava
        // inacessível justamente para quem ele foi feito.
        $igreja = Church::factory()->create();

        $this->actingAs(User::factory()->ofChurch($igreja)->create())
            ->get('/ump')
            ->assertSuccessful();
    }

    public function test_presidente_de_ump_nao_entra_no_painel_da_diretoria(): void
    {
        $igreja = Church::factory()->create();

        $this->actingAs(User::factory()->ofChurch($igreja)->create())
            ->get('/area-da-diretoria')
            ->assertForbidden();
    }

    public function test_usuario_sem_igreja_nao_entra_no_painel_da_ump(): void
    {
        $this->actingAs(User::factory()->create(['church_id' => null]))
            ->get('/ump')
            ->assertForbidden();
    }

    public function test_escolher_a_igreja_no_perfil_nao_da_acesso_ao_painel_da_ump(): void
    {
        // A trava que permite o campo "igreja" ficar aberto em /profile: quem
        // abre o /ump é `is_church_president`, marcado pela diretoria. Enquanto
        // as duas coisas eram a mesma coluna, qualquer jovem viraria presidente
        // só escolhendo a igreja no perfil.
        $igreja = Church::factory()->create();
        $jovem = User::factory()->create();

        $this->actingAs($jovem)->patch('/profile', [
            'name' => $jovem->name,
            'email' => $jovem->email,
            'church_id' => $igreja->id,
            'phone' => '84999999999',
        ])->assertSessionHasNoErrors();

        $jovem->refresh();

        $this->assertSame($igreja->id, $jovem->church_id);
        $this->assertFalse($jovem->is_church_president);

        $this->actingAs($jovem)->get('/ump')->assertForbidden();
    }

    public function test_perfil_nao_promove_a_presidente_de_ump(): void
    {
        $igreja = Church::factory()->create();
        $jovem = User::factory()->create();

        $this->actingAs($jovem)->patch('/profile', [
            'name' => $jovem->name,
            'email' => $jovem->email,
            'church_id' => $igreja->id,
            'is_church_president' => 1,
        ]);

        $this->assertFalse($jovem->fresh()->is_church_president);
    }

    public function test_presidente_sem_igreja_nao_entra_no_painel_da_ump(): void
    {
        // `is_church_president` sozinho não basta: sem igreja o escopo do painel
        // não teria a que se prender.
        $user = User::factory()->create(['church_id' => null]);
        $user->forceFill(['is_church_president' => true])->save();

        $this->actingAs($user)->get('/ump')->assertForbidden();
    }

    public function test_admin_entra_nos_dois_paineis(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/area-da-diretoria')->assertSuccessful();
        $this->actingAs($admin)->get('/ump')->assertSuccessful();
    }

    // ------------------------------------------------- inscrição do congresso

    public function test_ump_nao_consegue_forjar_igreja_nem_status_da_inscricao(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $minhaIgreja = Church::factory()->create();
        $outraIgreja = Church::factory()->create();
        $congresso = Event::factory()->congress()->create();
        $presidente = User::factory()->ofChurch($minhaIgreja)->create();

        Filament::setCurrentPanel('ump');

        Livewire::actingAs($presidente)
            ->test(CreateCongressSubscription::class)
            ->fillForm([
                'event_id' => $congresso->id,
                'receipt_path' => [UploadedFile::fake()->image('pix.jpg')],
                'documents' => [],
                'delegates' => [
                    ['name' => 'Fulano de Tal', 'email' => 'fulano@example.com', 'type' => 'delegado'],
                ],
            ])
            // Os dois campos saíram do schema; mandá-los mesmo assim não muda nada.
            ->set('data.church_id', $outraIgreja->id)
            ->set('data.status', 'aprovado')
            ->call('create')
            ->assertHasNoFormErrors();

        $inscricao = CongressSubscription::sole();

        $this->assertSame($minhaIgreja->id, $inscricao->church_id);
        $this->assertSame('pendente', $inscricao->status);
    }

    public function test_ump_nao_ve_inscricao_de_outra_igreja(): void
    {
        $minhaIgreja = Church::factory()->create();
        $outraIgreja = Church::factory()->create();
        $congresso = Event::factory()->congress()->create();

        $daOutra = CongressSubscription::create([
            'event_id' => $congresso->id,
            'church_id' => $outraIgreja->id,
            'status' => 'pendente',
        ]);

        Filament::setCurrentPanel('ump');

        $this->actingAs(User::factory()->ofChurch($minhaIgreja)->create())
            ->get(EditCongressSubscription::getUrl(['record' => $daOutra], panel: 'ump'))
            ->assertNotFound();
    }

    public function test_ump_nao_edita_inscricao_ja_aprovada(): void
    {
        $igreja = Church::factory()->create();
        $congresso = Event::factory()->congress()->create();

        $aprovada = CongressSubscription::create([
            'event_id' => $congresso->id,
            'church_id' => $igreja->id,
            'status' => 'aprovado',
        ]);

        Filament::setCurrentPanel('ump');

        $this->actingAs(User::factory()->ofChurch($igreja)->create())
            ->get(EditCongressSubscription::getUrl(['record' => $aprovada], panel: 'ump'))
            ->assertForbidden();
    }

    public function test_ump_nao_manda_duas_inscricoes_para_o_mesmo_congresso(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $igreja = Church::factory()->create();
        $congresso = Event::factory()->congress()->create();

        CongressSubscription::create([
            'event_id' => $congresso->id,
            'church_id' => $igreja->id,
            'status' => 'pendente',
        ]);

        Filament::setCurrentPanel('ump');

        Livewire::actingAs(User::factory()->ofChurch($igreja)->create())
            ->test(CreateCongressSubscription::class)
            ->fillForm([
                'event_id' => $congresso->id,
                'receipt_path' => [UploadedFile::fake()->image('pix.jpg')],
                'documents' => [],
                'delegates' => [
                    ['name' => 'Fulano de Tal', 'email' => 'fulano@example.com', 'type' => 'delegado'],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(1, CongressSubscription::count());
    }

    // ------------------------------------------------------------ contas

    public function test_ultimo_administrador_nao_pode_ser_excluido(): void
    {
        $unico = User::factory()->admin()->create();

        $this->expectException(RuntimeException::class);

        $unico->delete();
    }

    public function test_administrador_pode_ser_excluido_quando_ha_outro(): void
    {
        User::factory()->admin()->create();
        $segundo = User::factory()->admin()->create();

        $segundo->delete();

        $this->assertSame(1, User::where('is_admin', true)->count());
    }

    public function test_cadastro_publico_nao_cria_administrador(): void
    {
        // is_admin saiu do fillable justamente para isso.
        $this->post('/register', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'password' => 'senha-bem-comprida',
            'password_confirmation' => 'senha-bem-comprida',
            'is_admin' => 1,
        ]);

        $this->assertFalse(User::where('email', 'fulano@example.com')->value('is_admin'));
    }

    public function test_perfil_nao_escala_para_administrador(): void
    {
        $igreja = Church::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'is_admin' => 1,
            'church_id' => $igreja->id,
        ]);

        $user->refresh();

        $this->assertFalse((bool) $user->is_admin);
        // A igreja é dado do próprio usuário e ele pode gravar — o que ela não
        // faz é abrir painel nenhum (ver o teste de acesso ao /ump).
        $this->assertSame($igreja->id, $user->church_id);
        $this->actingAs($user)->get('/area-da-diretoria')->assertForbidden();
    }

    public function test_seeder_de_desenvolvimento_e_recusado_em_producao(): void
    {
        // Criava um admin com e-mail `a@a` e senha `123`.
        app()['env'] = 'production';

        $this->expectException(RuntimeException::class);

        (new DatabaseSeeder)->run();
    }
}
