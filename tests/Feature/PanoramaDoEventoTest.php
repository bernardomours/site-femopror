<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PanoramaDoEventoGraficos;
use App\Filament\Widgets\PanoramaDoEventoTotais;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\PanoramaDoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O painel de acompanhamento não pode ter nada específico de evento nenhum:
 * quem cadastra o evento não mexe em código, e um gráfico escrito à mão para a
 * Copa nasceria desatualizado no evento seguinte.
 */
class PanoramaDoEventoTest extends TestCase
{
    use RefreshDatabase;

    private function eventoComPerguntas(array $campos = []): Event
    {
        return Event::factory()->create([
            'custom_fields' => $campos ?: [
                ['question' => 'Quais esportes irá participar?', 'type' => 'checkbox', 'options' => "Futsal\nVôlei\nFIFA"],
                ['question' => 'Possui alguma restrição alimentar?', 'type' => 'checkbox', 'options' => "Sim\nNão"],
            ],
        ]);
    }

    private function inscrever(Event $evento, ?Church $igreja, array $respostas, string $status = 'pending', float $valor = 20): Registration
    {
        return Registration::factory()->create([
            'event_id' => $evento->id,
            'church_id' => $igreja?->id,
            'user_id' => User::factory()->create()->id,
            'payment_status' => $status,
            'amount_paid' => $valor,
            'custom_answers' => $respostas,
        ]);
    }

    // ------------------------------------------------ gráficos derivados

    public function test_cada_pergunta_do_evento_vira_um_grafico_sem_codigo_novo(): void
    {
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, [
            'Quais esportes irá participar?' => ['Futsal', 'FIFA'],
            'Possui alguma restrição alimentar?' => ['Não'],
        ]);

        $titulos = collect(PanoramaDoEvento::para($evento)->graficos())->pluck('titulo');

        $this->assertContains('Inscritos por igreja', $titulos);
        $this->assertContains('Quais esportes irá participar?', $titulos);
        $this->assertContains('Possui alguma restrição alimentar?', $titulos);
    }

    public function test_pergunta_nova_no_evento_aparece_sozinha_no_painel(): void
    {
        // É esta a promessa do recurso: a diretoria cadastra a pergunta e o
        // gráfico existe, sem deploy.
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, ['Quais esportes irá participar?' => ['Futsal']]);

        $antes = collect(PanoramaDoEvento::para($evento->fresh())->graficos())->pluck('titulo');
        $this->assertNotContains('Você é oficial de mesa?', $antes);

        $evento->update(['custom_fields' => [
            ...$evento->custom_fields,
            ['question' => 'Você é oficial de mesa?', 'type' => 'checkbox', 'options' => "Sim\nNão"],
        ]]);

        $this->inscrever($evento->fresh(), $igreja, ['Você é oficial de mesa?' => ['Sim']]);

        $depois = collect(PanoramaDoEvento::para($evento->fresh())->graficos())->pluck('titulo');
        $this->assertContains('Você é oficial de mesa?', $depois);
    }

    public function test_marcacoes_de_checkbox_sao_contadas_uma_a_uma(): void
    {
        // Checkbox é múltipla escolha: uma pessoa em dois esportes conta nos
        // dois. Somar por inscrição daria o total errado para a organização.
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, ['Quais esportes irá participar?' => ['Futsal', 'FIFA']]);
        $this->inscrever($evento, $igreja, ['Quais esportes irá participar?' => ['Futsal']]);

        $esportes = collect(PanoramaDoEvento::para($evento)->graficos())
            ->firstWhere('titulo', 'Quais esportes irá participar?');

        $contagem = array_combine($esportes['rotulos'], $esportes['valores']);

        $this->assertSame(2, $contagem['Futsal']);
        $this->assertSame(1, $contagem['FIFA']);
    }

    public function test_resposta_de_opcao_renomeada_continua_contando(): void
    {
        /*
         * `custom_answers` é gravado com o texto da pergunta e da opção. Se a
         * contagem partisse da lista de opções atual, renomear uma opção
         * zeraria silenciosamente quem já tinha respondido — e o painel
         * mostraria menos gente do que existe.
         */
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, ['Quais esportes irá participar?' => ['Futsal']]);

        $evento->update(['custom_fields' => [
            ['question' => 'Quais esportes irá participar?', 'type' => 'checkbox', 'options' => "Futsal de salão\nVôlei"],
            ['question' => 'Possui alguma restrição alimentar?', 'type' => 'checkbox', 'options' => "Sim\nNão"],
        ]]);

        $grafico = collect(PanoramaDoEvento::para($evento->fresh())->graficos())
            ->firstWhere('titulo', 'Quais esportes irá participar?');

        $this->assertContains('Futsal', $grafico['rotulos']);
    }

    public function test_quem_nao_respondeu_aparece_na_descricao_e_nao_como_coluna(): void
    {
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, ['Quais esportes irá participar?' => ['Futsal']]);
        $this->inscrever($evento, $igreja, []);

        $grafico = collect(PanoramaDoEvento::para($evento)->graficos())
            ->firstWhere('titulo', 'Quais esportes irá participar?');

        $this->assertSame(['Futsal'], $grafico['rotulos']);
        $this->assertStringContainsString('1 sem resposta', $grafico['subtitulo']);
    }

    public function test_campo_de_texto_livre_mostra_so_as_mais_frequentes(): void
    {
        // Texto livre é resposta única por pessoa: sem corte, o gráfico vira
        // uma coluna de altura 1 para cada inscrito, o que não informa nada.
        $evento = $this->eventoComPerguntas([
            ['question' => 'Alguma observação?', 'type' => 'text', 'options' => ''],
        ]);
        $igreja = Church::factory()->create();

        for ($i = 1; $i <= 14; $i++) {
            $this->inscrever($evento, $igreja, ['Alguma observação?' => 'observação '.$i]);
        }

        $grafico = collect(PanoramaDoEvento::para($evento)->graficos())
            ->firstWhere('titulo', 'Alguma observação?');

        $this->assertCount(10, $grafico['rotulos']);
        $this->assertStringContainsString('mais frequentes', $grafico['subtitulo']);
    }

    public function test_grafico_sem_nenhuma_resposta_nao_e_desenhado(): void
    {
        $evento = $this->eventoComPerguntas();
        $this->inscrever($evento, Church::factory()->create(), []);

        $titulos = collect(PanoramaDoEvento::para($evento)->graficos())->pluck('titulo');

        $this->assertContains('Inscritos por igreja', $titulos);
        $this->assertNotContains('Quais esportes irá participar?', $titulos);
    }

    public function test_inscrito_sem_igreja_nao_some_da_contagem(): void
    {
        $evento = $this->eventoComPerguntas();
        $this->inscrever($evento, null, ['Quais esportes irá participar?' => ['Futsal']]);

        $grafico = collect(PanoramaDoEvento::para($evento)->graficos())
            ->firstWhere('titulo', 'Inscritos por igreja');

        $this->assertSame(['Sem igreja informada'], $grafico['rotulos']);
        $this->assertSame([1], $grafico['valores']);
    }

    // ------------------------------------------------------------ totais

    public function test_totais_separam_caixa_confirmado_do_que_falta_entrar(): void
    {
        $evento = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($evento, $igreja, [], 'paid', 30);
        $this->inscrever($evento, $igreja, [], 'pending', 20);
        $this->inscrever($evento, $igreja, [], 'failed', 50);

        $t = PanoramaDoEvento::para($evento)->totais();

        $this->assertSame(3, $t['inscritos']);
        $this->assertSame(1, $t['confirmados']);
        // A cancelada não conta como pendente nem entra no que falta receber.
        $this->assertSame(1, $t['aguardando']);
        $this->assertSame(30.0, $t['arrecadado']);
        $this->assertSame(20.0, $t['a_receber']);
    }

    public function test_numeros_sao_de_um_evento_so(): void
    {
        // O defeito que o painel de congresso já teve: somar todos os eventos
        // de todos os anos e nunca responder "quantos vêm neste?".
        $copa = $this->eventoComPerguntas();
        $outro = $this->eventoComPerguntas();
        $igreja = Church::factory()->create();

        $this->inscrever($copa, $igreja, []);
        $this->inscrever($outro, $igreja, []);
        $this->inscrever($outro, $igreja, []);

        $this->assertSame(1, PanoramaDoEvento::para($copa)->totais()['inscritos']);
        $this->assertSame(2, PanoramaDoEvento::para($outro)->totais()['inscritos']);
    }

    // ------------------------------------------------------------- tela

    public function test_seletor_da_pagina_lista_os_eventos_e_ja_vem_preenchido(): void
    {
        $evento = $this->eventoComPerguntas();

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(Dashboard::class)
            ->assertSee($evento->title)
            // Sem valor inicial, o painel abriria em branco esperando um clique.
            ->assertSet('filters.eventId', $evento->id);
    }

    /**
     * Os widgets são componentes Livewire próprios e carregam sob demanda, então
     * o HTML da página traz só o esqueleto — é neles que o recorte por evento
     * precisa ser verificado.
     */
    public function test_widget_desenha_apenas_o_evento_selecionado(): void
    {
        $copa = $this->eventoComPerguntas();
        $igreja = Church::factory()->create(['name' => 'IP Central de Mossoró']);
        $this->inscrever($copa, $igreja, ['Quais esportes irá participar?' => ['Futsal']]);

        $outro = $this->eventoComPerguntas();
        $this->inscrever($outro, Church::factory()->create(['name' => 'IP do Planalto']), []);

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(PanoramaDoEventoGraficos::class, ['pageFilters' => ['eventId' => $copa->id]])
            ->assertSee('IP Central de Mossoró')
            ->assertSee('Quais esportes irá participar?')
            ->assertDontSee('IP do Planalto');
    }

    public function test_widget_de_totais_acompanha_o_evento_selecionado(): void
    {
        $copa = $this->eventoComPerguntas();
        $this->inscrever($copa, Church::factory()->create(), [], 'paid', 30);

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(PanoramaDoEventoTotais::class, ['pageFilters' => ['eventId' => $copa->id]])
            ->assertSee('R$ 30,00')
            ->assertSee($copa->title);
    }

    public function test_sem_evento_escolhido_o_painel_explica_em_vez_de_ficar_em_branco(): void
    {
        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(PanoramaDoEventoTotais::class, ['pageFilters' => []])
            ->assertSee('Nenhum evento selecionado');
    }

    public function test_usuario_comum_nao_entra_no_painel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/area-da-diretoria')
            ->assertForbidden();
    }
}
