<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InscricaoEventoTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $estado = []): Event
    {
        return Event::factory()->create($estado);
    }

    private function comprovante(): UploadedFile
    {
        return UploadedFile::fake()->image('pix.jpg');
    }

    public function test_visitante_deslogado_nao_consegue_gravar_inscricao(): void
    {
        // O formulário fica dentro de @auth, mas o método continua endereçável
        // por /livewire/update.
        $evento = $this->evento();

        Livewire::test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->call('register')
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
        $igreja = Church::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', $igreja->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertForbidden();

        $this->assertSame(0, Registration::count());
    }

    public function test_evento_com_inscricao_ainda_por_abrir_nao_aceita_inscricao(): void
    {
        $evento = $this->evento(['opening_date' => now()->addWeek()]);
        $igreja = Church::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', $igreja->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertForbidden();
    }

    public function test_nao_permite_duas_inscricoes_no_mesmo_evento(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $evento = $this->evento();
        $igreja = Church::factory()->create();
        $user = User::factory()->create();

        Registration::factory()->create([
            'event_id' => $evento->id,
            'user_id' => $user->id,
            'church_id' => $igreja->id,
        ]);

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', $igreja->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertHasErrors('name');

        $this->assertSame(1, Registration::count());
    }

    public function test_evento_gratuito_nao_exige_comprovante(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $evento = $this->evento(['price' => 0, 'requires_receipt' => false]);
        $igreja = Church::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', $igreja->id)
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame(1, Registration::count());
        $this->assertSame('0.00', Registration::first()->amount_paid);
    }

    public function test_evento_pago_continua_exigindo_comprovante(): void
    {
        $evento = $this->evento(['price' => 50, 'requires_receipt' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->call('register')
            ->assertHasErrors(['receipt' => 'required']);
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
            ->call('register')
            ->assertHasErrors(['name', 'email', 'phone', 'church_id']);
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
        Storage::fake(config('femopror.uploads.disk'));

        // O acréscimo saía de um regex sobre a string devolvida pelo cliente:
        // bastava mandar um rótulo inventado para pagar menos.
        $evento = $this->evento([
            'price' => 100,
            'custom_fields' => [
                ['question' => 'Camisa', 'type' => 'select', 'options' => 'P (+30,00), M (+30,00)'],
            ],
        ]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id]);

        $chave = $evento->customFieldDefinitions()[0]['key'];

        $componente->set("respostas.$chave", 'Camisa de graça (+0,00)');

        // O total ignora o que não está no cadastro do evento...
        $this->assertSame(100.0, $componente->instance()->precoFinal);

        // ...e a gravação é recusada.
        $componente
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertHasErrors("respostas.$chave");

        $this->assertSame(0, Registration::count());
    }

    public function test_valor_cobrado_fica_congelado_na_inscricao(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $evento = $this->evento(['price' => 75, 'requires_receipt' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '(84) 99999-9999')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertHasNoErrors();

        $inscricao = Registration::first();

        $this->assertSame('75.00', $inscricao->amount_paid);
        // Reajustar o evento depois não mexe no que foi cobrado.
        $evento->update(['price' => 120]);
        $this->assertSame('75.00', $inscricao->fresh()->amount_paid);
    }

    public function test_telefone_com_mascara_e_gravado_so_com_digitos(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $evento = $this->evento();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '(84) 99135-0289')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame('84991350289', Registration::first()->phone);
    }

    public function test_comprovante_vai_para_o_disco_privado(): void
    {
        Storage::fake(config('femopror.uploads.disk'));
        Storage::fake('public');

        $evento = $this->evento();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->set('receipt', $this->comprovante())
            ->call('register')
            ->assertHasNoErrors();

        $caminho = Registration::first()->receipt_path;
        $disco = config('femopror.uploads.disk');

        // O disco de upload é configurável (`local` em dev, `r2` em produção).
        // O que o teste garante é o que não pode mudar: o comprovante vai para
        // o disco configurado e NUNCA para o público.
        Storage::disk($disco)->assertExists($caminho);
        Storage::disk('public')->assertMissing($caminho);

        $this->assertNotSame('public', $disco);
        $this->assertNotSame('public', config("filesystems.disks.{$disco}.visibility", 'private'));
    }

    public function test_pergunta_com_ponto_no_texto_nao_quebra_o_formulario(): void
    {
        // A resposta era indexada pelo texto da pergunta no wire:model, e o
        // ponto virava aninhamento de array.
        $evento = $this->evento([
            'custom_fields' => [
                ['question' => 'Tem restrição alimentar? Ex.: sim, não.', 'type' => 'text'],
            ],
        ]);

        $definicao = $evento->customFieldDefinitions()[0];

        $this->assertStringNotContainsString('.', $definicao['key']);

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->assertOk();
    }
}
