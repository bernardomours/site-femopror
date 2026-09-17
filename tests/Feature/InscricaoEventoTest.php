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
 * Inscrição em duas etapas: ① dados (salva a inscrição) → ② pagamento
 * (anexa o comprovante).
 */
class InscricaoEventoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
        Mail::fake();
    }

    private function evento(array $estado = []): Event
    {
        return Event::factory()->create($estado);
    }

    private function comprovante(): UploadedFile
    {
        return UploadedFile::fake()->image('pix.jpg');
    }

    /** Preenche a etapa 1 com dados válidos. */
    private function preencher(Testable $componente, ?Church $igreja = null): Testable
    {
        return $componente
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', ($igreja ?? Church::factory()->create())->id);
    }

    // ------------------------------------------------------------ acesso

    public function test_visitante_deslogado_nao_consegue_salvar_inscricao(): void
    {
        // O formulário fica dentro de @auth, mas o método continua endereçável.
        $evento = $this->evento();

        $this->preencher(Livewire::test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertForbidden();

        $this->assertSame(0, Registration::count());
    }

    public function test_evento_em_rascunho_nao_e_acessivel(): void
    {
        $evento = $this->evento(['status' => 'draft']);

        $this->actingAs(User::factory()->create())
            ->get(route('events.show', $evento->id))
            ->assertNotFound();
    }

    public function test_evento_encerrado_nao_aceita_inscricao(): void
    {
        $evento = $this->evento(['status' => 'closed']);

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertForbidden();

        $this->assertSame(0, Registration::count());
    }

    public function test_evento_com_inscricao_ainda_por_abrir_nao_aceita_inscricao(): void
    {
        $evento = $this->evento(['opening_date' => now()->addWeek()]);

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertForbidden();
    }

    // -------------------------------------------------- etapa 1: dados

    public function test_ir_para_pagamento_salva_a_inscricao_antes_do_comprovante(): void
    {
        // A inscrição é salva ANTES do pagamento: pagar pelo celular é sair para
        // o app do banco, e a aba pode morrer no caminho.
        $evento = $this->evento(['price' => 40, 'requires_receipt' => true]);
        $user = User::factory()->create();

        $this->preencher(Livewire::actingAs($user)->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertHasNoErrors()
            ->assertSee('Sua vaga está reservada');

        $inscricao = Registration::sole();

        $this->assertSame($user->id, $inscricao->user_id);
        $this->assertNull($inscricao->receipt_path);
        $this->assertSame('pending', $inscricao->payment_status);
        $this->assertSame('40.00', $inscricao->amount_paid);
        $this->assertTrue($inscricao->isAwaitingReceipt());

        // O e-mail de "recebemos" só sai com o comprovante.
        Mail::assertNothingSent();
    }

    public function test_erros_de_validacao_aparecem_para_nome_email_e_igreja(): void
    {
        $evento = $this->evento();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'a')
            ->set('email', 'não-é-email')
            ->set('phone', '123')
            ->set('church_id', '')
            ->call('salvarDados')
            ->assertHasErrors(['name', 'email', 'phone', 'church_id']);

        $this->assertSame(0, Registration::count());
    }

    public function test_telefone_com_mascara_e_gravado_so_com_digitos(): void
    {
        $evento = $this->evento();

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->set('phone', '(84) 99135-0289')
            ->call('salvarDados')
            ->assertHasNoErrors();

        $this->assertSame('84991350289', Registration::sole()->phone);
    }

    public function test_preco_soma_o_adicional_da_opcao_escolhida(): void
    {
        $evento = $this->evento([
            'price' => 50,
            'custom_fields' => [
                ['question' => 'Qual o tamanho da camisa?', 'type' => 'select', 'options' => 'P, M (+30,00), G (+45)'],
            ],
        ]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id]);

        $chave = $evento->customFieldDefinitions()[0]['key'];

        $componente->set("respostas.$chave", 'M (+30,00)');
        $this->assertSame(80.0, $componente->instance()->precoFinal);

        $componente->set("respostas.$chave", 'G (+45)');
        $this->assertSame(95.0, $componente->instance()->precoFinal);
    }

    public function test_resposta_forjada_nao_muda_o_preco_nem_e_gravada(): void
    {
        // O acréscimo saía de um regex sobre a string devolvida pelo cliente:
        // bastava mandar um rótulo inventado para pagar menos.
        $evento = $this->evento([
            'price' => 100,
            'custom_fields' => [
                ['question' => 'Camisa', 'type' => 'select', 'options' => 'P (+30,00), M (+30,00)'],
            ],
        ]);

        $chave = $evento->customFieldDefinitions()[0]['key'];

        $componente = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set("respostas.$chave", 'Camisa de graça (+0,00)');

        $this->assertSame(100.0, $componente->instance()->precoFinal);

        $this->preencher($componente)
            ->call('salvarDados')
            ->assertHasErrors("respostas.$chave");

        $this->assertSame(0, Registration::count());
    }

    public function test_valor_cobrado_fica_congelado_na_inscricao(): void
    {
        $evento = $this->evento(['price' => 75]);

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertHasNoErrors();

        $inscricao = Registration::sole();
        $this->assertSame('75.00', $inscricao->amount_paid);

        // Reajustar o evento depois não mexe no que foi cobrado.
        $evento->update(['price' => 120]);
        $this->assertSame('75.00', $inscricao->fresh()->amount_paid);
    }

    public function test_pergunta_com_ponto_no_texto_nao_quebra_o_formulario(): void
    {
        $evento = $this->evento([
            'custom_fields' => [
                ['question' => 'Tem restrição alimentar? Ex.: sim, não.', 'type' => 'text'],
            ],
        ]);

        $this->assertStringNotContainsString('.', $evento->customFieldDefinitions()[0]['key']);

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->assertOk();
    }

    public function test_evento_gratuito_conclui_sem_etapa_de_pagamento(): void
    {
        $evento = $this->evento(['price' => 0, 'requires_receipt' => true]);

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->assertHasNoErrors()
            ->assertDontSee('Sua vaga está reservada');

        $inscricao = Registration::sole();

        $this->assertSame('0.00', $inscricao->amount_paid);
        $this->assertFalse($inscricao->isAwaitingReceipt());
        $this->assertSame('gratuita', $inscricao->statusKey());

        // Sem nada a pagar, o resumo sai já na etapa 1.
        Mail::assertSent(InscricaoRecebida::class);
    }

    // ------------------------------------------------ voltar e alterar

    public function test_alterar_dados_atualiza_a_mesma_inscricao(): void
    {
        $evento = $this->evento([
            'price' => 40,
            'custom_fields' => [
                ['question' => 'Modalidades', 'type' => 'checkbox', 'options' => 'Futsal (+5,00), Vôlei (+5,00)'],
            ],
        ]);

        $chave = $evento->customFieldDefinitions()[0]['key'];

        $componente = $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->set("respostas.$chave", ['Futsal (+5,00)'])
            ->call('salvarDados');

        $this->assertSame('45.00', Registration::sole()->amount_paid);

        $componente
            ->call('alterarDados')
            ->assertSet('editando', true)
            ->set("respostas.$chave", ['Futsal (+5,00)', 'Vôlei (+5,00)'])
            ->call('salvarDados')
            ->assertHasNoErrors()
            ->assertSet('editando', false);

        // Mesma inscrição, valor e respostas novos — nada de linha duplicada.
        $inscricao = Registration::sole();
        $this->assertSame('50.00', $inscricao->amount_paid);
        $this->assertSame(['Futsal (+5,00)', 'Vôlei (+5,00)'], $inscricao->custom_answers['Modalidades']);
    }

    public function test_nao_altera_dados_depois_de_enviar_comprovante(): void
    {
        // Depois do comprovante, mudar a modalidade mudaria o valor de um PIX já pago.
        $evento = $this->evento(['price' => 40, 'requires_receipt' => true]);

        $componente = $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $componente->call('alterarDados')->assertForbidden();
    }

    public function test_nao_permite_segunda_inscricao_depois_de_concluida(): void
    {
        $evento = $this->evento();
        $user = User::factory()->create();

        Registration::factory()->create([
            'event_id' => $evento->id,
            'user_id' => $user->id,
            'receipt_path' => 'receipts/ja-enviado.jpg',
        ]);

        // Forçando a etapa 1 por request, mesmo com a tela mostrando "já inscrito".
        $this->preencher(Livewire::actingAs($user)->test('event-show', ['id' => $evento->id]))
            ->set('editando', true)
            ->call('salvarDados')
            ->assertHasErrors('name');

        $this->assertSame(1, Registration::count());
    }

    // ------------------------------------------- etapa 2: comprovante

    public function test_evento_pago_exige_comprovante_para_finalizar(): void
    {
        $evento = $this->evento(['price' => 50, 'requires_receipt' => true]);

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->call('enviarComprovante')
            ->assertHasErrors(['receipt' => 'required']);

        $this->assertNull(Registration::sole()->receipt_path);
    }

    public function test_comprovante_vai_para_o_disco_privado(): void
    {
        Storage::fake('public');

        $evento = $this->evento();

        $this->preencher(Livewire::actingAs(User::factory()->create())->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados')
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $caminho = Registration::sole()->receipt_path;
        $disco = config('femopror.uploads.disk');

        // O que não pode mudar: vai para o disco configurado e NUNCA para o público.
        Storage::disk($disco)->assertExists($caminho);
        Storage::disk('public')->assertMissing($caminho);

        $this->assertNotSame('public', $disco);
        $this->assertNotSame('public', config("filesystems.disks.{$disco}.visibility", 'private'));
    }

    public function test_quem_fecha_a_aba_retoma_direto_no_pagamento(): void
    {
        // O cenário que motivou salvar antes de pagar: a pessoa salva os dados,
        // sai para o app do banco, e a aba morre. Reabrindo a página, ela tem
        // que cair no pagamento — não num formulário vazio.
        $evento = $this->evento(['price' => 40, 'requires_receipt' => true]);
        $user = User::factory()->create();

        $this->preencher(Livewire::actingAs($user)->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados');

        // Página nova, componente novo.
        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->assertSee('Sua vaga está reservada')
            ->assertSee('Finalizar inscrição')
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $this->assertNotNull(Registration::sole()->receipt_path);
        Mail::assertSent(InscricaoRecebida::class);
    }

    public function test_comprovante_ainda_pode_ser_enviado_depois_que_as_inscricoes_fecham(): void
    {
        // Salvou com inscrições abertas e foi pagar; elas fecharam nesse meio-tempo.
        $evento = $this->evento(['price' => 40, 'requires_receipt' => true]);
        $user = User::factory()->create();

        $this->preencher(Livewire::actingAs($user)->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados');

        $evento->update(['status' => 'closed']);

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $this->assertNotNull(Registration::sole()->receipt_path);
    }

    public function test_enviar_comprovante_sem_inscricao_salva_e_recusado(): void
    {
        $evento = $this->evento();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertForbidden();
    }

    public function test_nao_da_para_anexar_comprovante_na_inscricao_de_outra_pessoa(): void
    {
        $evento = $this->evento(['price' => 40, 'requires_receipt' => true]);
        $dona = User::factory()->create();

        $this->preencher(Livewire::actingAs($dona)->test('event-show', ['id' => $evento->id]))
            ->call('salvarDados');

        // A inscrição é sempre buscada pelo usuário autenticado: não há id para forjar.
        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('receipt', $this->comprovante())
            ->call('enviarComprovante')
            ->assertForbidden();

        $this->assertNull(Registration::sole()->receipt_path);
    }
}
