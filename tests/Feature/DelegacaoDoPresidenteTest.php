<?php

namespace Tests\Feature;

use App\Filament\Ump\Resources\CongressSubscriptions\Pages\CreateCongressSubscription;
use App\Filament\Ump\Resources\CongressSubscriptions\Pages\EditCongressSubscription;
use App\Models\Church;
use App\Models\CongressSubscription;
use App\Models\Delegate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O presidente entra na própria delegação.
 *
 * Ele vai ao congresso como qualquer outro, mas precisava se digitar — e,
 * esquecendo, ficava de fora da delegação que ele mesmo enviou: sumia da
 * contagem da secretaria e do painel.
 */
class DelegacaoDoPresidenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
    }

    /**
     * O formulário exige comprovante e ao menos um documento; o foco destes
     * testes é a delegação, então o resto vai preenchido aqui.
     *
     * @param  list<array<string, mixed>>  $delegados
     * @return array<string, mixed>
     */
    private function dadosDaInscricao(Event $evento, array $delegados): array
    {
        return [
            'event_id' => $evento->id,
            'receipt_path' => [UploadedFile::fake()->create('pix.pdf', 50, 'application/pdf')],
            'documents' => [
                ['document_type' => 'delegados', 'file_path' => [UploadedFile::fake()->create('lista.pdf', 50, 'application/pdf')]],
            ],
            'delegates' => $delegados,
        ];
    }

    private function presidente(Church $igreja): User
    {
        return User::factory()->create([
            'name' => 'Ana Presidente',
            'email' => 'ana@exemplo.test',
            'church_id' => $igreja->id,
            'is_church_president' => true,
        ]);
    }

    public function test_formulario_de_nova_inscricao_ja_vem_com_o_presidente(): void
    {
        $igreja = Church::factory()->create();

        $delegados = $this->delegadosNoFormulario(
            Livewire::actingAs($this->presidente($igreja))->test(CreateCongressSubscription::class)
        );

        $this->assertCount(1, $delegados, 'a lista devia nascer com uma linha');
        $this->assertSame('Ana Presidente', $delegados[0]['name'] ?? null);
        $this->assertSame('ana@exemplo.test', $delegados[0]['email'] ?? null);
        $this->assertSame('delegado', $delegados[0]['type'] ?? null);
    }

    /**
     * As linhas do repeater, sem as chaves UUID que o Filament gera.
     *
     * `assertFormSet` com closure não serve aqui: ela precisa **retornar** o
     * estado esperado, não fazer asserções — um closure que assera devolve
     * nulls e o Filament os compara contra o estado real.
     *
     * @return list<array<string, mixed>>
     */
    private function delegadosNoFormulario(\Livewire\Features\SupportTesting\Testable $componente): array
    {
        return array_values($componente->get('data')['delegates'] ?? []);
    }

    public function test_presidente_e_gravado_junto_com_os_outros(): void
    {
        $igreja = Church::factory()->create();
        $evento = Event::factory()->congress()->create();

        Livewire::actingAs($this->presidente($igreja))
            ->test(CreateCongressSubscription::class)
            ->fillForm($this->dadosDaInscricao($evento, [
                ['name' => 'Ana Presidente', 'email' => 'ana@exemplo.test', 'type' => 'delegado'],
                ['name' => 'Bruno Delegado', 'email' => 'bruno@exemplo.test', 'type' => 'delegado'],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $nomes = Delegate::pluck('name')->sort()->values()->all();

        $this->assertSame(['Ana Presidente', 'Bruno Delegado'], $nomes);
    }

    public function test_delegado_do_presidente_fica_amarrado_a_conta_dele(): void
    {
        /*
         * O e-mail preenchido é o da conta, então o vínculo acontece sozinho:
         * `Delegate::booted()` casa delegado com usuário por e-mail. É isso
         * que faz a inscrição aparecer no painel do participante.
         */
        $igreja = Church::factory()->create();
        $presidente = $this->presidente($igreja);
        $evento = Event::factory()->congress()->create();

        Livewire::actingAs($presidente)
            ->test(CreateCongressSubscription::class)
            ->fillForm($this->dadosDaInscricao($evento, [
                ['name' => 'Ana Presidente', 'email' => 'ana@exemplo.test', 'type' => 'delegado'],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($presidente->id, Delegate::sole()->user_id);
    }

    public function test_inscricao_guarda_quem_enviou(): void
    {
        /*
         * A tabela guardava só a igreja: a secretaria sabia de qual UMP veio,
         * mas não com quem falar sobre uma pendência. Deduzir pelo presidente
         * atual não serve — a presidência troca, e o histórico passaria a
         * atribuir inscrição antiga a quem nem era presidente na época.
         */
        $igreja = Church::factory()->create();
        $presidente = $this->presidente($igreja);
        $evento = Event::factory()->congress()->create();

        Livewire::actingAs($presidente)
            ->test(CreateCongressSubscription::class)
            ->fillForm($this->dadosDaInscricao($evento, [
                ['name' => 'Ana Presidente', 'email' => 'ana@exemplo.test', 'type' => 'delegado'],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($presidente->id, CongressSubscription::sole()->submitted_by);
    }

    public function test_quem_enviou_nao_e_campo_do_formulario(): void
    {
        /*
         * `submitted_by` identifica o autor, e por isso não pode ser campo:
         * é definido no servidor, igual a `church_id` e `status`.
         *
         * O teste é sobre a **ausência no schema**, não sobre adulterar o
         * estado. `->set('data.submitted_by', …)` não simula ataque nenhum —
         * `getState()` devolve só o que está no schema, então o valor nunca
         * chega ao `mutateFormDataBeforeCreate()` e o teste passaria com ou
         * sem a trava (verificado por mutação). Já caímos nisso antes; está
         * nas armadilhas do CLAUDE.md.
         */
        $componente = Livewire::actingAs($this->presidente(Church::factory()->create()))
            ->test(CreateCongressSubscription::class);

        $componente->assertFormFieldDoesNotExist('submitted_by');
        $componente->assertFormFieldDoesNotExist('church_id');
        $componente->assertFormFieldDoesNotExist('status');
    }

    public function test_painel_da_diretoria_mostra_quem_enviou(): void
    {
        $igreja = Church::factory()->create();
        $presidente = $this->presidente($igreja);

        $inscricao = CongressSubscription::create([
            'church_id' => $igreja->id,
            'event_id' => Event::factory()->congress()->create()->id,
            'status' => 'pendente',
            'receipt_path' => 'congress-receipts/x.pdf',
        ]);
        $inscricao->forceFill(['submitted_by' => $presidente->id])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/area-da-diretoria/congress-subscriptions/{$inscricao->id}/edit")
            ->assertSuccessful()
            ->assertSee('Enviada por')
            ->assertSee('ana@exemplo.test');
    }

    public function test_inscricao_antiga_diz_que_nao_tem_registro_em_vez_de_chutar(): void
    {
        // Chutar o presidente atual da igreja viraria histórico errado.
        $igreja = Church::factory()->create();
        $this->presidente($igreja);

        $inscricao = CongressSubscription::create([
            'church_id' => $igreja->id,
            'event_id' => Event::factory()->congress()->create()->id,
            'status' => 'pendente',
            'receipt_path' => 'congress-receipts/x.pdf',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/area-da-diretoria/congress-subscriptions/{$inscricao->id}/edit")
            ->assertSuccessful()
            ->assertSee('Não registrado')
            ->assertDontSee('ana@exemplo.test');
    }

    public function test_presidente_pode_se_tirar_da_lista(): void
    {
        // Há presidente que organiza e não vai. Forçar no save desfaria a
        // escolha sem avisar — por isso é preenchimento, não imposição.
        $igreja = Church::factory()->create();
        $evento = Event::factory()->congress()->create();

        Livewire::actingAs($this->presidente($igreja))
            ->test(CreateCongressSubscription::class)
            ->fillForm($this->dadosDaInscricao($evento, [
                ['name' => 'Bruno Delegado', 'email' => 'bruno@exemplo.test', 'type' => 'delegado'],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['Bruno Delegado'], Delegate::pluck('name')->all());
    }

    public function test_edicao_nao_insere_o_presidente_de_novo(): void
    {
        // `default` do Filament vale só na criação. Se valesse na edição, toda
        // vez que a UMP abrisse a inscrição apareceria uma linha repetida.
        $igreja = Church::factory()->create();
        $presidente = $this->presidente($igreja);

        $inscricao = CongressSubscription::create([
            'church_id' => $igreja->id,
            'event_id' => Event::factory()->congress()->create()->id,
            'status' => 'pendente',
            'receipt_path' => 'congress-receipts/x.pdf',
        ]);

        $inscricao->delegates()->create([
            'name' => 'Bruno Delegado',
            'email' => 'bruno@exemplo.test',
            'type' => 'delegado',
        ]);

        $delegados = $this->delegadosNoFormulario(
            Livewire::actingAs($presidente)
                ->test(EditCongressSubscription::class, ['record' => $inscricao->id])
        );

        $this->assertCount(1, $delegados, 'a edição não pode acrescentar o presidente de novo');
        $this->assertSame('Bruno Delegado', $delegados[0]['name'] ?? null);
    }
}
