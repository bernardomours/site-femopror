<?php

namespace Tests\Feature;

use App\Mail\InscricaoRecebida;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mandou o arquivo errado: dá para ver o que foi enviado e trocar, enquanto a
 * tesouraria não confirmou o pagamento. E o comprovante aceita PDF.
 */
class TrocaDeComprovanteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
        Mail::fake();
    }

    private function inscrever(User $user, Event $evento, UploadedFile $arquivo): Testable
    {
        return Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->call('salvarDados')
            ->set('receipt', $arquivo)
            ->call('enviarComprovante');
    }

    // ------------------------------------------------------------- PDF

    public function test_comprovante_em_pdf_e_aceito(): void
    {
        // Vários bancos compartilham o comprovante como PDF; a regra `image`
        // recusava e a pessoa não conseguia concluir a inscrição.
        $evento = Event::factory()->create();

        $this->inscrever(
            User::factory()->create(),
            $evento,
            UploadedFile::fake()->create('comprovante.pdf', 200, 'application/pdf'),
        )->assertHasNoErrors();

        $inscricao = Registration::sole();

        $this->assertStringEndsWith('.pdf', $inscricao->receipt_path);
        $this->assertTrue($inscricao->receiptIsPdf());
        Storage::disk(config('femopror.uploads.disk'))->assertExists($inscricao->receipt_path);
    }

    public function test_arquivo_de_tipo_proibido_e_recusado(): void
    {
        $evento = Event::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->call('salvarDados')
            ->set('receipt', UploadedFile::fake()->create('script.php', 10, 'application/x-php'))
            ->call('enviarComprovante')
            ->assertHasErrors('receipt');

        $this->assertNull(Registration::sole()->receipt_path);
    }

    // -------------------------------------------------- ver o que enviou

    public function test_participante_consegue_abrir_o_proprio_comprovante(): void
    {
        // O arquivo fica em disco privado: sem link assinado a pessoa não tinha
        // como nem descobrir qual arquivo estava lá.
        $evento = Event::factory()->create();

        $this->inscrever(User::factory()->create(), $evento, UploadedFile::fake()->image('pix.jpg'));

        $inscricao = Registration::sole();
        $url = $inscricao->receiptUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString($inscricao->receipt_path, $url);

        // Que o link seja assinado e expire é verificado contra o disco real em
        // AuditoriaSegurancaTest; o Storage::fake daqui não assina nada.
    }

    public function test_sem_comprovante_nao_ha_link(): void
    {
        $this->assertNull(Registration::factory()->create(['receipt_path' => null])->receiptUrl());
    }

    // ------------------------------------------------------ trocar

    public function test_troca_substitui_o_arquivo_e_apaga_o_antigo(): void
    {
        $evento = Event::factory()->create();
        $user = User::factory()->create();
        $disco = Storage::disk(config('femopror.uploads.disk'));

        $componente = $this->inscrever($user, $evento, UploadedFile::fake()->image('errado.jpg'));

        $antigo = Registration::sole()->receipt_path;
        $disco->assertExists($antigo);

        $componente
            ->call('trocarComprovante')
            ->assertSet('substituindoComprovante', true)
            ->set('receipt', UploadedFile::fake()->create('certo.pdf', 150, 'application/pdf'))
            ->call('enviarComprovante')
            ->assertHasNoErrors()
            ->assertSet('substituindoComprovante', false);

        $novo = Registration::sole()->receipt_path;

        $this->assertNotSame($antigo, $novo);
        $disco->assertExists($novo);
        // Documento bancário sem dono não fica ocupando espaço pago.
        $disco->assertMissing($antigo);
    }

    public function test_troca_nao_manda_o_email_de_novo(): void
    {
        $evento = Event::factory()->create();

        $componente = $this->inscrever(User::factory()->create(), $evento, UploadedFile::fake()->image('errado.jpg'));

        Mail::assertSent(InscricaoRecebida::class, 1);

        $componente
            ->call('trocarComprovante')
            ->set('receipt', UploadedFile::fake()->image('certo.jpg'))
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        // Trocar arquivo não é inscrição nova: mandar o resumo de novo é ruído.
        Mail::assertSent(InscricaoRecebida::class, 1);
    }

    public function test_troca_mantem_valor_e_respostas(): void
    {
        $evento = Event::factory()->create([
            'price' => 40,
            'custom_fields' => [['question' => 'Modalidades', 'type' => 'checkbox', 'options' => 'Futsal (+5,00)']],
        ]);
        $chave = $evento->customFieldDefinitions()[0]['key'];
        $user = User::factory()->create();

        $componente = Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->set("respostas.$chave", ['Futsal (+5,00)'])
            ->call('salvarDados')
            ->set('receipt', UploadedFile::fake()->image('errado.jpg'))
            ->call('enviarComprovante');

        $componente
            ->call('trocarComprovante')
            ->set('receipt', UploadedFile::fake()->image('certo.jpg'))
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $inscricao = Registration::sole();

        $this->assertSame('45.00', $inscricao->amount_paid);
        $this->assertSame(['Futsal (+5,00)'], $inscricao->custom_answers['Modalidades']);
    }

    // -------------------------------------------- travas depois de pago

    public function test_nao_troca_depois_que_a_tesouraria_confirma(): void
    {
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $componente = $this->inscrever($user, $evento, UploadedFile::fake()->image('pix.jpg'));

        // A tesouraria confirma: o valor já foi conferido contra este arquivo.
        $inscricao = Registration::sole();
        $inscricao->update(['payment_status' => 'paid']);
        $original = $inscricao->receipt_path;

        $this->assertFalse($inscricao->fresh()->canReplaceReceipt());

        $componente->call('trocarComprovante')->assertForbidden();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('receipt', UploadedFile::fake()->image('outro.jpg'))
            ->call('enviarComprovante')
            ->assertForbidden();

        $this->assertSame($original, Registration::sole()->receipt_path);
    }

    public function test_confirmacao_no_meio_da_troca_nao_deixa_sobrescrever(): void
    {
        // A tela foi carregada com a inscrição pendente, mas a tesouraria
        // confirmou antes de o arquivo novo chegar.
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $componente = $this->inscrever($user, $evento, UploadedFile::fake()->image('pix.jpg'));
        $componente->call('trocarComprovante');

        $original = Registration::sole()->receipt_path;
        Registration::sole()->update(['payment_status' => 'paid']);

        $componente
            ->set('receipt', UploadedFile::fake()->image('tarde-demais.jpg'))
            ->call('enviarComprovante')
            ->assertForbidden();

        $this->assertSame($original, Registration::sole()->receipt_path);
    }

    public function test_nao_troca_comprovante_de_outra_pessoa(): void
    {
        $evento = Event::factory()->create();
        $dona = User::factory()->create();

        $this->inscrever($dona, $evento, UploadedFile::fake()->image('pix.jpg'));
        $original = Registration::sole()->receipt_path;

        // A inscrição é buscada pelo usuário autenticado: não há id para forjar.
        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->call('trocarComprovante')
            ->assertForbidden();

        $this->assertSame($original, Registration::sole()->receipt_path);
    }
}
