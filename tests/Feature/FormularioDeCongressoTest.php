<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\PerguntasPadraoDoCongresso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O formulário padrão de inscrição do congresso.
 *
 * As perguntas padrão viram `custom_fields` do evento, iguais às digitadas à
 * mão — não existe caminho paralelo de renderização. Estes testes guardam isso:
 * se um dia o padrão passar a ser tratado como caso especial, eles quebram.
 */
class FormularioDeCongressoTest extends TestCase
{
    use RefreshDatabase;

    private function congresso(): Event
    {
        return Event::factory()->create([
            'is_congress' => true,
            'price' => 70,
            'custom_fields' => PerguntasPadraoDoCongresso::campos(),
        ]);
    }

    private function inscrever(Event $evento, User $user, array $respostas = []): \Livewire\Features\SupportTesting\Testable
    {
        $componente = Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id);

        foreach ($respostas as $chave => $valor) {
            $componente->set("respostas.{$chave}", $valor);
        }

        return $componente->call('salvarDados');
    }

    private function chaveDe(Event $evento, string $trecho): string
    {
        foreach ($evento->customFieldDefinitions() as $campo) {
            if (str_contains($campo['question'], $trecho)) {
                return $campo['key'];
            }
        }

        $this->fail("pergunta com [{$trecho}] não existe no evento");
    }

    // -------------------------------------------- as perguntas padrão

    public function test_perguntas_padrao_aparecem_no_formulario(): void
    {
        $evento = $this->congresso();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->assertSee('Você vai como delegado ou visitante?')
            ->assertSee('Diretoria ou secretário (FEMOPROR)')
            ->assertSee('Você possui alguma alergia ou restrição alimentar? Se sim, qual?');
    }

    public function test_padrao_nao_repete_o_que_o_formulario_ja_pergunta(): void
    {
        /*
         * Nome, e-mail, WhatsApp e igreja são campos fixos. Repeti-los como
         * pergunta personalizada faria o participante responder duas vezes e
         * gravaria a igreja em dois lugares — e é `registrations.church_id`
         * que alimenta o gráfico "inscritos por igreja".
         */
        $perguntas = collect(PerguntasPadraoDoCongresso::campos())->pluck('question')->implode(' | ');

        foreach (['Nome completo', 'E-mail', 'WhatsApp', 'Telefone', 'Qual UMP', 'igreja'] as $proibida) {
            $this->assertStringNotContainsStringIgnoringCase($proibida, $perguntas);
        }
    }

    public function test_parenteses_sem_numero_nao_viram_acrescimo(): void
    {
        // "Diretoria ou secretário (FEMOPROR)" tem parênteses, e é com
        // parênteses que o sistema marca acréscimo: `Camisa M (+30,00)`.
        // Sem dígito lá dentro, não é dinheiro.
        $evento = $this->congresso();

        $categoria = collect($evento->customFieldDefinitions())
            ->firstWhere('question', 'Você vai como delegado ou visitante?');

        $this->assertSame([0.0, 0.0, 0.0], array_column($categoria['options'], 'surcharge'));
        $this->assertSame(
            ['Delegado', 'Visitante', 'Diretoria ou secretário (FEMOPROR)'],
            array_column($categoria['options'], 'label'),
        );
    }

    public function test_categoria_escolhida_fica_gravada_na_inscricao(): void
    {
        $evento = $this->congresso();

        $this->inscrever($evento, User::factory()->create(), [
            $this->chaveDe($evento, 'delegado ou visitante') => 'Delegado',
            $this->chaveDe($evento, 'alergia') => 'Intolerância a lactose',
        ])->assertHasNoErrors();

        $respostas = Registration::sole()->custom_answers;

        $this->assertSame('Delegado', $respostas['Você vai como delegado ou visitante?']);
        $this->assertSame('Intolerância a lactose', $respostas['Você possui alguma alergia ou restrição alimentar? Se sim, qual?']);
    }

    public function test_categoria_fora_da_lista_e_recusada(): void
    {
        // `respostas` é propriedade pública: o valor vem do navegador.
        $evento = $this->congresso();

        $this->inscrever($evento, User::factory()->create(), [
            $this->chaveDe($evento, 'delegado ou visitante') => 'Presidente da federação',
            $this->chaveDe($evento, 'alergia') => 'Nenhuma',
        ])->assertHasErrors($this->chaveNoFormato($evento, 'delegado ou visitante'));

        $this->assertSame(0, Registration::count());
    }

    private function chaveNoFormato(Event $evento, string $trecho): string
    {
        return 'respostas.'.$this->chaveDe($evento, $trecho);
    }

    // ------------------------------------------- pergunta obrigatória

    public function test_pergunta_obrigatoria_em_branco_barra_a_inscricao(): void
    {
        $evento = $this->congresso();

        $this->inscrever($evento, User::factory()->create(), [
            $this->chaveDe($evento, 'delegado ou visitante') => 'Visitante',
            // alergia deixada em branco de propósito
        ])->assertHasErrors($this->chaveNoFormato($evento, 'alergia'));

        $this->assertSame(0, Registration::count());
    }

    public function test_mensagem_de_obrigatoria_cita_a_pergunta_e_nao_o_nome_tecnico(): void
    {
        // Sem mensagem própria sairia "O campo respostas.0-voce-vai-como é
        // obrigatório", que não diz nada para quem está se inscrevendo.
        $evento = $this->congresso();

        $componente = $this->inscrever($evento, User::factory()->create(), []);

        $erros = $componente->errors()->get($this->chaveNoFormato($evento, 'delegado ou visitante'));

        $this->assertStringContainsString('Você vai como delegado ou visitante?', $erros[0] ?? '');
        $this->assertStringNotContainsString('respostas.', $erros[0] ?? '');
    }

    public function test_pergunta_opcional_continua_opcional(): void
    {
        // Evento antigo não tem a chave `required` no JSON: ausente é opcional,
        // senão todo evento já cadastrado mudaria de comportamento.
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Qual o tamanho da sua camisa?', 'type' => 'select', 'options' => 'P, M, G'],
            ],
        ]);

        $this->inscrever($evento, User::factory()->create())->assertHasNoErrors();

        $this->assertSame(1, Registration::count());
    }

    public function test_checkbox_obrigatorio_exige_ao_menos_uma_marcacao(): void
    {
        // Checkbox em branco chega como array vazio, e `required` não pega isso.
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Quais oficinas?', 'type' => 'checkbox', 'options' => 'Louvor, Teatro', 'required' => true],
            ],
        ]);

        $this->inscrever($evento, User::factory()->create())
            ->assertHasErrors($this->chaveNoFormato($evento, 'oficinas'));

        $this->assertSame(0, Registration::count());
    }

    public function test_asterisco_aparece_so_na_pergunta_obrigatoria(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Pergunta exigida', 'type' => 'text', 'options' => '', 'required' => true],
                ['question' => 'Pergunta opcional', 'type' => 'text', 'options' => ''],
            ],
        ]);

        $html = Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->html();

        /*
         * Cada pergunta é uma `<legend>`, e é dentro dela que o asterisco mora.
         * Procurar "pergunta seguida de span vermelho" no HTML inteiro não
         * serve: o Livewire insere comentários `<!--[if BLOCK]-->` em volta de
         * todo `@if`, e um `.*?` atravessaria para a pergunta seguinte.
         */
        preg_match_all('#<legend[^>]*>(.*?)</legend>#s', $html, $achados);

        $legendas = [];

        foreach ($achados[1] as $conteudo) {
            $texto = trim(preg_replace('/\s+/', ' ', strip_tags($conteudo)));
            $legendas[$texto] = str_contains($conteudo, 'text-red-600');
        }

        $exigida = collect($legendas)->first(fn ($v, $k) => str_starts_with($k, 'Pergunta exigida'));
        $opcional = collect($legendas)->first(fn ($v, $k) => str_starts_with($k, 'Pergunta opcional'));

        $this->assertTrue($exigida, 'a pergunta obrigatória não está marcada com asterisco');
        $this->assertFalse($opcional, 'a pergunta opcional apareceu marcada como obrigatória');
    }

    // ------------------------------------ o cadastro do evento no painel

    public function test_tela_de_cadastro_de_evento_abre_e_oferece_as_perguntas_padrao(): void
    {
        /*
         * Este teste existe por um erro concreto: a ação foi escrita como
         * `Repeater::headerActions()`, que **não existe** no Filament 5. A tela
         * de criar evento passou a devolver 500 e a suíte inteira continuou
         * verde, porque nenhum teste renderizava o formulário do painel. Um
         * `assertSuccessful()` aqui vale por qualquer asserção fina.
         */
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/area-da-diretoria/events/create')
            ->assertSuccessful()
            ->assertSee('Carregar modelo')
            ->assertSee('Perguntas personalizadas para este evento');
    }

    public function test_carregar_modelo_preenche_as_perguntas_do_evento(): void
    {
        /*
         * Esta ação tem formulário próprio (o modal que escolhe o modelo), e
         * isso muda o escopo do `Set` injetado: ele passa a apontar para o
         * estado **do modal**. A primeira versão gravava numa chave que
         * ninguém lê — o modal fechava e o repeater continuava vazio, sem erro
         * nenhum na tela. Nenhum teste de rota pegaria isso.
         */
        $modelo = \App\Models\FormTemplate::sole();

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(\App\Filament\Resources\Events\Pages\CreateEvent::class)
            ->callFormComponentAction('acoesDeModelo', 'carregarModelo', ['modelo' => $modelo->id])
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet(fn (array $estado) => [
                $this->assertCount(2, $estado['custom_fields'] ?? []),
                $this->assertSame(
                    'Você vai como delegado ou visitante?',
                    array_values($estado['custom_fields'])[0]['question'] ?? null,
                ),
            ]);
    }

    public function test_salvar_como_modelo_guarda_as_perguntas_da_tela(): void
    {
        $perguntas = [
            ['question' => 'Tamanho da camisa', 'type' => 'select', 'required' => false, 'help' => '', 'options' => [
                ['label' => 'P', 'surcharge' => 0],
                ['label' => 'GG', 'surcharge' => 10],
            ]],
        ];

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(\App\Filament\Resources\Events\Pages\CreateEvent::class)
            ->fillForm(['custom_fields' => $perguntas])
            ->callFormComponentAction('acoesDeModelo', 'salvarComoModelo', ['nome' => 'Acampamento'])
            ->assertHasNoFormComponentActionErrors();

        $modelo = \App\Models\FormTemplate::where('name', 'Acampamento')->sole();

        $this->assertSame('Tamanho da camisa', $modelo->fields[0]['question']);
        $this->assertSame(10, (int) $modelo->fields[0]['options'][1]['surcharge']);
    }

    public function test_salvar_como_modelo_funciona_tambem_a_partir_de_um_evento_existente(): void
    {
        /*
         * O `unique()` do Filament, dentro de um Resource, acrescenta sozinho
         * o "ignore" do registro em edição. Como o registro é o **evento** e a
         * tabela checada é `form_templates`, a consulta saía
         * `from form_templates where name = ? and events.id <> 7` e estourava.
         *
         * A versão anterior deste teste usava a tela de **criar**, onde não há
         * registro para ignorar — e passava com o bug no lugar.
         */
        $evento = $this->congresso();

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(\App\Filament\Resources\Events\Pages\EditEvent::class, ['record' => $evento->id])
            ->callFormComponentAction('acoesDeModelo', 'salvarComoModelo', ['nome' => 'A partir do evento'])
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame(2, \App\Models\FormTemplate::where('name', 'A partir do evento')->sole()->quantidadeDePerguntas());
    }

    public function test_modelo_com_nome_repetido_vira_erro_no_campo(): void
    {
        // Sem a regra, o índice único do banco barraria com exceção em vez de
        // mensagem — o mesmo padrão já documentado no projeto.
        $evento = $this->congresso();

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(\App\Filament\Resources\Events\Pages\EditEvent::class, ['record' => $evento->id])
            ->callFormComponentAction('acoesDeModelo', 'salvarComoModelo', ['nome' => 'Congresso — padrão'])
            ->assertHasFormComponentActionErrors(['nome']);

        $this->assertSame(1, \App\Models\FormTemplate::count());
    }

    public function test_modelo_do_congresso_ja_vem_cadastrado(): void
    {
        // Ele era uma classe PHP: mudar uma palavra exigia deploy. Agora é
        // linha no banco, semeada por migration.
        $modelo = \App\Models\FormTemplate::where('name', 'Congresso — padrão')->first();

        $this->assertNotNull($modelo, 'o modelo padrão do congresso não foi semeado');
        $this->assertSame(2, $modelo->quantidadeDePerguntas());
    }

    public function test_telas_de_modelo_de_formulario_abrem(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $modelo = \App\Models\FormTemplate::sole();

        $this->actingAs($admin)->get('/area-da-diretoria/form-templates')->assertSuccessful();
        $this->actingAs($admin)->get('/area-da-diretoria/form-templates/create')->assertSuccessful();
        $this->actingAs($admin)->get("/area-da-diretoria/form-templates/{$modelo->id}/edit")->assertSuccessful();
    }

    public function test_usuario_comum_nao_acessa_os_modelos(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/area-da-diretoria/form-templates')
            ->assertForbidden();
    }

    public function test_evento_com_opcoes_no_formato_antigo_ainda_abre_para_edicao(): void
    {
        /*
         * O editor usa repeater para as opções, e repeater ligado a uma
         * **string** quebra a tela. Todo evento anterior ao conversor tem
         * `options` em string, então sem a normalização no `mutateFormDataBeforeFill`
         * o Edit de cada um deles devolveria 500.
         */
        $admin = User::factory()->create(['is_admin' => true]);

        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Modalidade', 'type' => 'checkbox', 'options' => 'Futsal(R$5,00), Vôlei(R$5,00)'],
            ],
        ]);

        $this->actingAs($admin)
            ->get("/area-da-diretoria/events/{$evento->id}/edit")
            ->assertSuccessful();
    }

    public function test_tela_de_edicao_de_evento_tambem_abre(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $evento = $this->congresso();

        $this->actingAs($admin)
            ->get("/area-da-diretoria/events/{$evento->id}/edit")
            ->assertSuccessful();
    }

    // ------------------------------- a tela de categoria não existe mais

    public function test_congresso_abre_direto_no_formulario(): void
    {
        /*
         * A tela "Escolha sua categoria" mandava quem clicasse em "Sou
         * Delegado" para o `/ump`, que é painel de presidente: o jovem delegado
         * tomava 403 e não se inscrevia. Agora a categoria é pergunta.
         */
        $evento = $this->congresso();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->assertDontSee('Escolha sua categoria')
            ->assertDontSee('Acesso exclusivo para Presidentes')
            ->assertSee('Seus dados');
    }

    public function test_delegado_se_inscreve_sozinho_sem_passar_pelo_painel_da_ump(): void
    {
        $evento = $this->congresso();

        $this->inscrever($evento, User::factory()->create(), [
            $this->chaveDe($evento, 'delegado ou visitante') => 'Delegado',
            $this->chaveDe($evento, 'alergia') => 'Nenhuma',
        ])->assertHasNoErrors();

        // Valor do congresso cobrado normalmente, como qualquer inscrição.
        $this->assertSame('70.00', Registration::sole()->amount_paid);
    }
}
