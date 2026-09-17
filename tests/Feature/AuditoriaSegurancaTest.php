<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Varredura das quatro frentes pedidas: rate limit, SQL injection, acesso a
 * área alheia e vazamento no que chega ao navegador.
 */
class AuditoriaSegurancaTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- rate limit

    public function test_cadastro_publico_tem_teto_de_requisicoes(): void
    {
        // Sem teto, cada requisição virava uma conta — e o cadastro não exige
        // verificação de e-mail.
        $ultimo = null;

        for ($i = 0; $i < 22; $i++) {
            $ultimo = $this->post('/register', [
                'name' => 'Fulano de Tal',
                'email' => "fulano{$i}@example.com",
                'password' => 'senha-bem-comprida',
                'password_confirmation' => 'senha-bem-comprida',
            ]);

            $this->flushSession();
        }

        $this->assertSame(429, $ultimo->getStatusCode());
        $this->assertLessThan(22, User::count());
    }

    public function test_recuperacao_de_senha_tem_teto_de_requisicoes(): void
    {
        // Cada pedido dispara um e-mail: sem teto dá para queimar a cota do SMTP.
        $ultimo = null;

        for ($i = 0; $i < 7; $i++) {
            $ultimo = $this->post('/forgot-password', ['email' => "alvo{$i}@example.com"]);
        }

        $this->assertSame(429, $ultimo->getStatusCode());
    }

    public function test_login_tem_teto_mesmo_variando_o_email(): void
    {
        // O LoginRequest conta por e-mail+IP; trocar o e-mail a cada tentativa
        // escapava daquela contagem.
        $ultimo = null;

        for ($i = 0; $i < 22; $i++) {
            $ultimo = $this->post('/login', [
                'email' => "tentativa{$i}@example.com",
                'password' => 'chute',
            ]);

            $this->flushSession();
        }

        $this->assertSame(429, $ultimo->getStatusCode());
    }

    public function test_rotas_sensiveis_declaram_throttle(): void
    {
        $esperado = [
            'POST register',
            'POST login',
            'POST forgot-password',
            'POST reset-password',
            'POST confirm-password',
            'PUT password',
        ];

        foreach ($esperado as $alvo) {
            [$metodo, $uri] = explode(' ', $alvo);

            $rota = collect(Route::getRoutes())->first(
                fn ($r) => $r->uri() === $uri && in_array($metodo, $r->methods(), true)
            );

            $this->assertNotNull($rota, "rota [{$alvo}] não existe");
            $this->assertTrue(
                collect($rota->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
                "rota [{$alvo}] está sem throttle",
            );
        }
    }

    public function test_endpoint_do_livewire_tem_teto(): void
    {
        $rota = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === 'livewire/update');

        $this->assertNotNull($rota);
        $this->assertTrue(
            collect($rota->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
            'livewire/update está sem throttle',
        );
    }

    public function test_gravar_inscricao_tem_teto_por_usuario(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $evento = Event::factory()->create();
        $user = User::factory()->create();
        $igreja = Church::factory()->create();

        RateLimiter::clear('inscricao:'.$user->id);

        for ($i = 0; $i < 11; $i++) {
            RateLimiter::hit('inscricao:'.$user->id, 300);
        }

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', $igreja->id)
            ->call('salvarDados')
            ->assertHasErrors('name');

        $this->assertSame(0, Registration::count());
    }

    // --------------------------------------------------- SQL injection

    public function test_texto_malicioso_e_gravado_como_texto(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        // Tudo passa por query builder com binding. O payload tem que sobreviver
        // como texto comum, e as tabelas continuarem de pé.
        $payload = "'; DROP TABLE registrations; --";

        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Observação', 'type' => 'text']],
        ]);
        $chave = $evento->customFieldDefinitions()[0]['key'];

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', "Fulano{$payload}")
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->set("respostas.$chave", $payload)
            ->call('salvarDados')
            ->assertHasNoErrors();

        $inscricao = Registration::sole();

        $this->assertSame("Fulano{$payload}", $inscricao->name);
        $this->assertSame($payload, $inscricao->custom_answers['Observação']);
        $this->assertSame(1, Registration::count());
    }

    public function test_busca_por_email_com_aspas_nao_quebra_a_consulta(): void
    {
        // O vínculo de delegado usa whereRaw('LOWER(email) = ?') — com binding.
        $user = User::factory()->create(['email' => "a'or'1'='1@example.com"]);

        $this->actingAs($user)->get(route('dashboard'))->assertSuccessful();
    }

    // ------------------------------------------------- áreas restritas

    public static function rotasRestritas(): array
    {
        return [
            'painel da diretoria' => ['/area-da-diretoria'],
            'inscrições (diretoria)' => ['/area-da-diretoria/registrations'],
            'usuários (diretoria)' => ['/area-da-diretoria/users'],
            'igrejas (diretoria)' => ['/area-da-diretoria/churches'],
            'painel da UMP' => ['/ump'],
            'inscrições do congresso' => ['/ump/congress-subscriptions'],
        ];
    }

    #[DataProvider('rotasRestritas')]
    public function test_usuario_comum_nao_entra_em_area_restrita(string $rota): void
    {
        $this->actingAs(User::factory()->create())->get($rota)->assertForbidden();
    }

    #[DataProvider('rotasRestritas')]
    public function test_visitante_deslogado_e_mandado_para_o_login(string $rota): void
    {
        $this->get($rota)->assertRedirect();
    }

    public function test_presidente_de_ump_nao_alcanca_recursos_da_diretoria(): void
    {
        $igreja = Church::factory()->create();
        $presidente = User::factory()->ofChurch($igreja)->create();

        // O painel /ump só registra os recursos de App\Filament\Ump: o de
        // usuários não existe lá, e o da diretoria é barrado pelo painel.
        $this->actingAs($presidente)->get('/ump/users')->assertNotFound();
        $this->actingAs($presidente)->get('/area-da-diretoria/users')->assertForbidden();
    }

    public function test_arquivo_privado_so_abre_com_assinatura_valida(): void
    {
        // Sem fake de propósito: o que está sendo testado é a rota real que
        // serve o disco privado, e ela lê o disco configurado de verdade.
        $caminho = 'testes/auditoria-'.uniqid().'.txt';
        Storage::disk('local')->put($caminho, 'conteudo sigiloso');

        try {
            // Sem assinatura, recusa.
            $this->get('/storage/'.$caminho)->assertForbidden();

            $assinada = Storage::disk('local')->temporaryUrl($caminho, now()->addMinutes(5));
            $this->get($assinada)->assertSuccessful();

            // Assinatura adulterada não vale.
            $this->get(preg_replace('/signature=\w+/', 'signature=00000000', $assinada))->assertForbidden();
        } finally {
            Storage::disk('local')->delete($caminho);
        }
    }

    // ------------------------------------------- vazamento no front-end

    public function test_html_nao_entrega_dados_de_outro_participante(): void
    {
        $evento = Event::factory()->create();
        $igreja = Church::factory()->create();

        $outro = User::factory()->create();
        Registration::factory()->create([
            'event_id' => $evento->id,
            'user_id' => $outro->id,
            'church_id' => $igreja->id,
            'name' => 'Vizinho Secreto',
            'email' => 'vizinho.secreto@example.com',
            'phone' => '84988887777',
            'receipt_path' => 'receipts/comprovante-do-vizinho.jpg',
            'amount_paid' => 999.55,
        ]);

        $eu = User::factory()->create();
        $vazamentos = ['Vizinho Secreto', 'vizinho.secreto@example.com', '84988887777', 'comprovante-do-vizinho', '999,55'];

        foreach ([route('events.show', $evento->id), route('dashboard'), route('profile.edit'), route('home')] as $url) {
            $html = $this->actingAs($eu)->get($url)->assertSuccessful()->getContent();

            foreach ($vazamentos as $sensivel) {
                $this->assertStringNotContainsString($sensivel, $html, "[{$sensivel}] vazou em {$url}");
            }
        }
    }

    public function test_pagina_publica_nao_expoe_evento_em_rascunho(): void
    {
        Event::factory()->draft()->create(['title' => 'Evento Secreto da Diretoria']);

        $this->get(route('home'))
            ->assertSuccessful()
            ->assertDontSee('Evento Secreto da Diretoria');
    }

    public function test_estado_publico_do_componente_so_tem_dados_do_proprio_usuario(): void
    {
        // Toda propriedade pública viaja no snapshot dentro do HTML. Um id de
        // registro ali seria adulterável; por isso a inscrição é sempre buscada
        // pelo usuário autenticado, e o eventId é #[Locked].
        $evento = Event::factory()->create();

        $publicas = collect((new \ReflectionClass(
            Livewire::test('event-show', ['id' => $evento->id])->instance()
        ))->getProperties(\ReflectionProperty::IS_PUBLIC))
            ->map(fn ($p) => $p->getName())
            ->reject(fn ($n) => in_array($n, ['id'], true))
            ->values()
            ->all();

        sort($publicas);

        $this->assertSame(
            [
                'church_id', 'editando', 'email', 'eventId', 'isVisitor', 'name',
                'phone', 'receipt', 'respostas', 'substituindoComprovante',
            ],
            $publicas,
            'apareceu propriedade pública nova no componente: confira se ela pode ser adulterada pelo navegador',
        );

        // `editando` e `substituindoComprovante` são interruptores de tela: o
        // navegador pode ligá-los à vontade, porque quem decide se a operação
        // vale são `salvarDados()`, `trocarComprovante()` e `enviarComprovante()`,
        // que reconsultam a inscrição do usuário autenticado a cada chamada.
    }

    public function test_evento_id_e_travado_contra_adulteracao(): void
    {
        $meuEvento = Event::factory()->create();
        $outroEvento = Event::factory()->create();

        // #[Locked]: trocar o evento pelo navegador derruba a requisição em vez
        // de deixar a pessoa operar sobre outro evento.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $meuEvento->id])
            ->set('eventId', $outroEvento->id);
    }
}
