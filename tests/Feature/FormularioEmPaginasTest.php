<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O formulário dividido em páginas.
 *
 * A etapa macro (preenchimento × pagamento) continua derivada do banco; o que
 * é propriedade é só a página corrente, e isso não afrouxa nada — `salvarDados()`
 * revalida o formulário inteiro antes de gravar.
 */
class FormularioEmPaginasTest extends TestCase
{
    use RefreshDatabase;

    private function eventoEmPaginas(): Event
    {
        return Event::factory()->create([
            'price' => 70,
            'form_pages' => [
                ['title' => 'Informações adicionais', 'description' => 'Sobre a sua participação'],
                ['title' => 'Documentos', 'description' => ''],
            ],
            'custom_fields' => [
                ['question' => 'Categoria', 'type' => 'radio', 'required' => true, 'page' => 1, 'options' => [
                    ['label' => 'Delegado', 'surcharge' => 0],
                    ['label' => 'Visitante', 'surcharge' => 0],
                ]],
                ['question' => 'Restrição alimentar', 'type' => 'text', 'required' => true, 'page' => 1],
                ['question' => 'Autorização dos pais', 'type' => 'text', 'page' => 2],
            ],
        ]);
    }

    private function naPrimeiraPagina(Event $evento): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id);
    }

    private function chaveDe(Event $evento, string $trecho): string
    {
        foreach ($evento->customFieldDefinitions() as $campo) {
            if (str_contains($campo['question'], $trecho)) {
                return $campo['key'];
            }
        }

        $this->fail("pergunta [{$trecho}] não existe");
    }

    // ------------------------------------------------- as páginas

    public function test_seus_dados_e_sempre_a_primeira_pagina(): void
    {
        // Ela não vem do cadastro: nome, e-mail, WhatsApp e igreja existem em
        // todo evento, e sem eles a inscrição não identifica ninguém.
        $paginas = $this->eventoEmPaginas()->formPages();

        $this->assertSame('Seus dados', $paginas[0]['title']);
        $this->assertSame('Informações adicionais', $paginas[1]['title']);
        $this->assertSame('Documentos', $paginas[2]['title']);
    }

    public function test_evento_sem_paginas_cadastradas_fica_em_tela_unica(): void
    {
        // O comportamento de todo evento anterior a esta coluna.
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Camisa', 'type' => 'text']],
        ]);

        $this->assertCount(1, $evento->formPages());
        $this->assertSame(0, $evento->customFieldDefinitions()[0]['page']);
    }

    public function test_cada_pagina_mostra_so_as_proprias_perguntas(): void
    {
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->assertSee('Seus dados')
            ->assertDontSee('Categoria')
            ->assertDontSee('Autorização dos pais');
    }

    public function test_avancar_mostra_a_pagina_seguinte(): void
    {
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->call('proximaPagina')
            ->assertHasNoErrors()
            ->assertSet('pagina', 1)
            ->assertSee('Informações adicionais')
            ->assertSee('Sobre a sua participação')
            ->assertSee('Categoria')
            ->assertDontSee('Autorização dos pais');
    }

    public function test_nao_avanca_com_campo_da_pagina_atual_invalido(): void
    {
        $evento = $this->eventoEmPaginas();

        Livewire::actingAs(User::factory()->create())
            ->test('event-show', ['id' => $evento->id])
            ->set('name', '')
            ->call('proximaPagina')
            ->assertHasErrors('name')
            ->assertSet('pagina', 0);
    }

    public function test_avancar_nao_cobra_pergunta_de_pagina_que_ainda_nao_apareceu(): void
    {
        /*
         * Validar o formulário inteiro a cada passo mostraria erro de campo
         * que a pessoa ainda nem viu — e prenderia todo mundo na página 1.
         */
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->call('proximaPagina')
            ->assertHasNoErrors()
            ->assertSet('pagina', 1);
    }

    public function test_voltar_preserva_o_que_foi_digitado(): void
    {
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->call('proximaPagina')
            ->set('respostas.'.$this->chaveDe($evento, 'Categoria'), 'Delegado')
            ->call('paginaAnterior')
            ->assertSet('pagina', 0)
            ->assertSet('name', 'Fulano de Tal')
            ->call('proximaPagina')
            ->assertSet('respostas.'.$this->chaveDe($evento, 'Categoria'), 'Delegado');
    }

    public function test_clicar_num_passo_so_volta_nunca_pula_adiante(): void
    {
        // Pular adiante pelo stepper contornaria a validação da página atual.
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->call('irParaPagina', 2)
            ->assertSet('pagina', 0)
            ->call('proximaPagina')
            // A página 2 só libera depois de responder o que é obrigatório
            // nela — é essa validação que o salto pelo stepper contornaria.
            ->set('respostas.'.$this->chaveDe($evento, 'Categoria'), 'Delegado')
            ->set('respostas.'.$this->chaveDe($evento, 'Restrição'), 'Nenhuma')
            ->call('proximaPagina')
            ->assertSet('pagina', 2)
            ->call('irParaPagina', 0)
            ->assertSet('pagina', 0);
    }

    public function test_percurso_inteiro_grava_as_respostas_de_todas_as_paginas(): void
    {
        $evento = $this->eventoEmPaginas();

        $this->naPrimeiraPagina($evento)
            ->call('proximaPagina')
            ->set('respostas.'.$this->chaveDe($evento, 'Categoria'), 'Delegado')
            ->set('respostas.'.$this->chaveDe($evento, 'Restrição'), 'Nenhuma')
            ->call('proximaPagina')
            ->set('respostas.'.$this->chaveDe($evento, 'Autorização'), 'Enviada por e-mail')
            ->call('salvarDados')
            ->assertHasNoErrors();

        $respostas = Registration::sole()->custom_answers;

        $this->assertSame('Delegado', $respostas['Categoria']);
        $this->assertSame('Nenhuma', $respostas['Restrição alimentar']);
        $this->assertSame('Enviada por e-mail', $respostas['Autorização dos pais']);
    }

    public function test_alterar_dados_mostra_tudo_numa_tela_so(): void
    {
        /*
         * `salvarDados()` regrava `custom_answers` inteiro. Se a edição fosse
         * paginada, o que não estivesse em tela seria gravado em branco — quem
         * voltasse só para corrigir o telefone perderia as outras respostas.
         */
        $evento = $this->eventoEmPaginas();
        $user = User::factory()->create();
        $igreja = Church::factory()->create();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')->set('church_id', $igreja->id)
            ->call('proximaPagina')
            ->set('respostas.'.$this->chaveDe($evento, 'Categoria'), 'Delegado')
            ->set('respostas.'.$this->chaveDe($evento, 'Restrição'), 'Nenhuma')
            ->call('proximaPagina')
            ->call('salvarDados')
            ->assertHasNoErrors();

        Livewire::actingAs($user->fresh())
            ->test('event-show', ['id' => $evento->id])
            ->call('alterarDados')
            ->assertSet('pagina', 0)
            // Perguntas de todas as páginas em tela ao mesmo tempo.
            ->assertSee('Categoria')
            ->assertSee('Autorização dos pais')
            ->set('phone', '84988887777')
            ->call('salvarDados')
            ->assertHasNoErrors();

        $this->assertSame('Delegado', Registration::sole()->custom_answers['Categoria']);
        $this->assertSame('84988887777', Registration::sole()->phone);
    }

    public function test_stepper_lista_as_paginas_e_o_pagamento(): void
    {
        $evento = $this->eventoEmPaginas();

        $html = $this->naPrimeiraPagina($evento)->html();

        // Dentro do elemento, não no HTML inteiro: "Pagamento" e "Seus dados"
        // também aparecem noutros pontos da tela, e a ordem global não diz
        // nada sobre a ordem dos passos.
        preg_match('#<ol class="hidden lg:flex.*?</ol>#s', $html, $lista);

        $this->assertNotEmpty($lista, 'a lista de etapas não foi renderizada');

        $this->assertSame(
            '1 Seus dados 2 Informações adicionais 3 Documentos 4 Pagamento',
            trim(preg_replace('/\s+/', ' ', strip_tags($lista[0]))),
        );

        /*
         * No celular a lista não cabe — quatro passos dão ~580px numa tela de
         * 375px — e rolagem lateral é padrão que quase ninguém percebe. Lá vai
         * o contador, que diz a mesma coisa numa linha.
         */
        $this->assertStringContainsString('Passo 1 de 4', $html);
    }

    public function test_evento_gratuito_de_pagina_unica_nao_mostra_stepper(): void
    {
        // Um passo só não informa nada; só ocupa a dobra.
        $evento = Event::factory()->create(['price' => 0, 'custom_fields' => []]);

        $this->naPrimeiraPagina($evento)
            ->assertDontSeeHtml('aria-label="Etapas da inscrição"');
    }

    public function test_pergunta_apontando_para_pagina_inexistente_nao_some(): void
    {
        /*
         * A diretoria apaga uma página e esquece a pergunta. Ela cai na
         * primeira: fica no lugar errado, mas aparece. Pergunta invisível é
         * resposta que nunca chega, e ninguém descobre até o dia do evento.
         */
        $evento = Event::factory()->create([
            'form_pages' => [['title' => 'Adicionais']],
            'custom_fields' => [['question' => 'Órfã', 'type' => 'text', 'page' => 7]],
        ]);

        $this->assertSame(0, $evento->customFieldDefinitions()[0]['page']);

        $this->naPrimeiraPagina($evento)->assertSee('Órfã');
    }
}
