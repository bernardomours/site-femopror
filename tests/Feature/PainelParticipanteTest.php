<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\CongressSubscription;
use App\Models\Delegate;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelParticipanteTest extends TestCase
{
    use RefreshDatabase;

    private function inscricaoDeCongresso(Church $igreja): CongressSubscription
    {
        return CongressSubscription::create([
            'event_id' => Event::factory()->congress()->create()->id,
            'church_id' => $igreja->id,
            'status' => 'pendente',
        ]);
    }

    public function test_dashboard_abre_para_quem_e_delegado(): void
    {
        // Regressão: a rota carregava `with('congressSubscription.church')`
        // sobre uma relação que o model não definia. Todo delegado de verdade
        // tomava 500 ao abrir o painel — só quem não era delegado via a tela.
        $igreja = Church::factory()->create();
        $user = User::factory()->create();

        Delegate::create([
            'congress_subscription_id' => $this->inscricaoDeCongresso($igreja)->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => 'delegado',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee($igreja->name);
    }

    public function test_delegado_continua_vinculado_depois_de_trocar_o_email(): void
    {
        // O vínculo era só pelo e-mail digitado pela UMP: trocar o e-mail no
        // perfil fazia a inscrição de delegado sumir do painel.
        $igreja = Church::factory()->create();
        $user = User::factory()->create(['email' => 'antigo@example.com']);

        Delegate::create([
            'congress_subscription_id' => $this->inscricaoDeCongresso($igreja)->id,
            'name' => $user->name,
            'email' => 'antigo@example.com',
            'type' => 'delegado',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'novo@example.com',
        ]);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee($igreja->name);
    }

    public function test_delegacao_cadastrada_antes_da_conta_e_amarrada_no_cadastro(): void
    {
        $igreja = Church::factory()->create();

        $delegado = Delegate::create([
            'congress_subscription_id' => $this->inscricaoDeCongresso($igreja)->id,
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'type' => 'delegado',
        ]);

        $this->assertNull($delegado->user_id);

        $user = User::factory()->create(['email' => 'fulano@example.com']);

        $this->assertSame($user->id, $delegado->fresh()->user_id);
    }

    public function test_participante_ve_somente_as_proprias_inscricoes(): void
    {
        $meu = User::factory()->create();
        $outro = User::factory()->create();

        $minhaInscricao = Registration::factory()->create([
            'user_id' => $meu->id,
            'event_id' => Event::factory()->create(['title' => 'Acampamento da Mocidade'])->id,
        ]);

        Registration::factory()->create([
            'user_id' => $outro->id,
            'event_id' => Event::factory()->create(['title' => 'Retiro Secreto de Outro'])->id,
        ]);

        $this->actingAs($meu)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Acampamento da Mocidade')
            ->assertDontSee('Retiro Secreto de Outro');
    }

    public function test_dashboard_exige_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
