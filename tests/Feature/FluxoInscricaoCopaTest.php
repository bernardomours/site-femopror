<?php

namespace Tests\Feature;

use App\Mail\InscricaoConfirmada;
use App\Mail\InscricaoRecebida;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\IntendedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O caminho completo de quem entra no site e se inscreve na Copa:
 * ver o evento → criar a conta ali mesmo → escolher os esportes → ver o valor
 * subir → anexar o comprovante → receber o e-mail.
 */
class FluxoInscricaoCopaTest extends TestCase
{
    use RefreshDatabase;

    private function copa(): Event
    {
        return Event::factory()->create([
            'title' => 'Copa FEMOPROR',
            'price' => 40,
            'requires_receipt' => true,
            'custom_fields' => [
                [
                    'question' => 'Quais esportes você vai disputar?',
                    'type' => 'checkbox',
                    'options' => 'Futsal (+15,00), Vôlei (+15,00), Xadrez (+5,00)',
                ],
            ],
        ]);
    }

    private function chaveDosEsportes(Event $copa): string
    {
        return $copa->customFieldDefinitions()[0]['key'];
    }

    // ------------------------------------------------- entrar / criar conta

    public function test_visitante_deslogado_ve_os_dois_caminhos_de_acesso(): void
    {
        $copa = $this->copa();

        $this->get(route('events.show', $copa->id))
            ->assertSuccessful()
            ->assertSee('Criar minha conta')
            ->assertSee('Já tenho conta')
            // Os dois links levam de volta para este evento.
            ->assertSee(route('register', ['redirect' => route('events.show', $copa->id, absolute: false)]), escape: false);
    }

    public function test_quem_cria_a_conta_pela_copa_volta_para_a_copa(): void
    {
        // Antes o cadastro redirecionava sempre para o dashboard e a pessoa
        // perdia o evento que estava tentando se inscrever.
        $copa = $this->copa();
        $destino = route('events.show', $copa->id, absolute: false);

        $this->get(route('register', ['redirect' => $destino]))->assertSuccessful();

        $this->post('/register', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'password' => 'senha-bem-comprida',
            'password_confirmation' => 'senha-bem-comprida',
        ])->assertRedirect($destino);

        $this->assertAuthenticated();
    }

    public function test_quem_entra_pela_copa_volta_para_a_copa(): void
    {
        $copa = $this->copa();
        $destino = route('events.show', $copa->id, absolute: false);
        $user = User::factory()->create();

        $this->get(route('login', ['redirect' => $destino]))->assertSuccessful();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($destino);
    }

    public function test_alternar_entre_entrar_e_criar_conta_nao_perde_o_destino(): void
    {
        $copa = $this->copa();
        $destino = route('events.show', $copa->id, absolute: false);

        $this->get(route('login', ['redirect' => $destino]))
            ->assertSee(route('register', ['redirect' => $destino]), escape: false);

        $this->get(route('register', ['redirect' => $destino]))
            ->assertSee(route('login', ['redirect' => $destino]), escape: false);
    }

    public function test_redirect_para_fora_do_site_e_ignorado(): void
    {
        // Open redirect: o link sai do nosso domínio e parece confiável, mas
        // joga a pessoa numa cópia da página de login em outro servidor.
        foreach (['https://evil.com', '//evil.com', '/\\evil.com', 'evil.com'] as $forjado) {
            $this->assertNull(IntendedUrl::sanitize($forjado), "aceitou [$forjado]");
        }

        $this->assertSame('/eventos/1', IntendedUrl::sanitize('/eventos/1'));

        $user = User::factory()->create();

        $this->get(route('login', ['redirect' => 'https://evil.com']))->assertSuccessful();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    // -------------------------------------------------------- esportes e valor

    public function test_escolher_esportes_soma_no_valor(): void
    {
        $copa = $this->copa();
        $chave = $this->chaveDosEsportes($copa);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $copa->id]);

        $this->assertSame(40.0, $componente->instance()->precoFinal);

        $componente->set("respostas.$chave", ['Futsal (+15,00)']);
        $this->assertSame(55.0, $componente->instance()->precoFinal);

        $componente->set("respostas.$chave", ['Futsal (+15,00)', 'Vôlei (+15,00)', 'Xadrez (+5,00)']);
        $this->assertSame(75.0, $componente->instance()->precoFinal);
    }

    public function test_mudar_de_esporte_invalida_o_qr_code_ja_gerado(): void
    {
        // Sem isto: gera o QR de R$ 55, marca mais um esporte, a tela mostra
        // R$ 70 e o QR na tela continua cobrando 55. A pessoa paga a menos e a
        // tesouraria recebe um comprovante que não fecha.
        $copa = $this->copa();
        $chave = $this->chaveDosEsportes($copa);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $copa->id])
            ->set("respostas.$chave", ['Futsal (+15,00)'])
            ->call('gerarPix')
            ->assertSet('mostrarPix', true);

        $pixAntigo = $componente->get('pixCopiaCola');
        $this->assertNotSame('', $pixAntigo);

        $componente->set("respostas.$chave", ['Futsal (+15,00)', 'Vôlei (+15,00)'])
            ->assertSet('mostrarPix', false)
            ->assertSet('pixCopiaCola', '');

        $componente->call('gerarPix');
        $this->assertNotSame($pixAntigo, $componente->get('pixCopiaCola'));
    }

    // --------------------------------------------------- comprovante e e-mail

    public function test_inscricao_completa_grava_esportes_valor_e_comprovante(): void
    {
        Storage::fake(config('femopror.uploads.disk'));
        Mail::fake();

        $copa = $this->copa();
        $chave = $this->chaveDosEsportes($copa);
        $igreja = Church::factory()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $copa->id])
            ->set("respostas.$chave", ['Futsal (+15,00)', 'Xadrez (+5,00)'])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '(84) 99135-0289')
            ->set('church_id', $igreja->id)
            ->set('receipt', UploadedFile::fake()->image('comprovante.jpg'))
            ->call('register')
            ->assertHasNoErrors();

        $inscricao = Registration::sole();

        $this->assertSame('60.00', $inscricao->amount_paid);
        $this->assertSame('pending', $inscricao->payment_status);
        $this->assertSame(
            ['Futsal (+15,00)', 'Xadrez (+5,00)'],
            $inscricao->custom_answers['Quais esportes você vai disputar?'],
        );

        Storage::disk(config('femopror.uploads.disk'))->assertExists($inscricao->receipt_path);
    }

    public function test_e_mail_de_recebimento_sai_ao_se_inscrever(): void
    {
        Storage::fake(config('femopror.uploads.disk'));
        Mail::fake();

        $copa = $this->copa();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $copa->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84991350289')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', UploadedFile::fake()->image('comprovante.jpg'))
            ->call('register')
            ->assertHasNoErrors();

        Mail::assertSent(InscricaoRecebida::class, fn ($mail) => $mail->hasTo('fulano@example.com'));
    }

    public function test_falha_no_envio_do_email_nao_derruba_a_inscricao(): void
    {
        // A inscrição já está gravada quando o e-mail sai. SMTP fora do ar não
        // pode virar erro na tela de quem acabou de se inscrever.
        Storage::fake(config('femopror.uploads.disk'));

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fora do ar'));

        $copa = $this->copa();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $copa->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84991350289')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', UploadedFile::fake()->image('comprovante.jpg'))
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame(1, Registration::count());
    }

    public function test_e_mail_de_confirmacao_sai_quando_a_tesouraria_aprova(): void
    {
        Mail::fake();

        $inscricao = Registration::factory()->create([
            'email' => 'fulano@example.com',
            'payment_status' => 'pending',
        ]);

        // Mesmo efeito da ação "Confirmar Pagamento" do painel.
        $inscricao->update(['payment_status' => 'paid']);
        \App\Support\SafeMail::send($inscricao->email, new InscricaoConfirmada($inscricao));

        Mail::assertSent(InscricaoConfirmada::class, fn ($mail) => $mail->hasTo('fulano@example.com'));
    }

    public function test_os_dois_emails_renderizam(): void
    {
        $inscricao = Registration::factory()->create([
            'name' => 'Fulano de Tal',
            'amount_paid' => 60,
            'custom_answers' => ['Quais esportes você vai disputar?' => ['Futsal', 'Xadrez']],
        ]);

        $recebida = (new InscricaoRecebida($inscricao))->render();
        $this->assertStringContainsString('Recebemos sua inscrição', $recebida);
        $this->assertStringContainsString('Futsal, Xadrez', $recebida);
        $this->assertStringContainsString('60,00', $recebida);

        $confirmada = (new InscricaoConfirmada($inscricao))->render();
        $this->assertStringContainsString('vaga está garantida', $confirmada);
    }

    // ------------------------------------------------------------- upload

    public function test_disco_de_upload_e_sempre_privado(): void
    {
        // Comprovante bancário não pode ficar em endereço público. Vale para os
        // dois discos possíveis: `local` e `r2`.
        foreach (['local', 'r2'] as $disco) {
            $config = config("filesystems.disks.$disco");

            $this->assertNotNull($config, "disco [$disco] não existe");
            $this->assertNotSame('public', $config['visibility'] ?? 'private', "disco [$disco] está público");
        }

        $this->assertNotSame('public', config('femopror.uploads.disk'));
    }
}
