<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Como as respostas aparecem para quem lê — participante e tesouraria.
 *
 * Resposta de pergunta do tipo arquivo guarda o **caminho**. Impresso como
 * está, aparecia `respostas/xY3k….pdf` na tela: inútil para quem quer abrir o
 * documento, e expondo o nome interno do arquivo.
 */
class ExibicaoDeRespostasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
    }

    private function inscricaoComArquivo(string $arquivo = 'respostas/abc123.pdf'): Registration
    {
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Relatório da Presidência', 'type' => 'file'],
                ['question' => 'Detalhe sua função no congresso', 'type' => 'text'],
            ],
        ]);

        Storage::disk(config('femopror.uploads.disk'))->put($arquivo, 'conteudo');

        return Registration::factory()->create([
            'event_id' => $evento->id,
            'church_id' => Church::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            // Sem comprovante: é no painel de pagamento que a página do evento
            // repete os dados enviados. Com comprovante ela mostra só o estado
            // "Você está inscrito!", sem as respostas.
            'payment_status' => 'pending',
            'receipt_path' => null,
            'custom_answers' => [
                'Relatório da Presidência' => $arquivo,
                'Detalhe sua função no congresso' => 'Delegado',
            ],
        ]);
    }

    public function test_resposta_de_arquivo_vira_link_e_nao_caminho(): void
    {
        $inscricao = $this->inscricaoComArquivo();

        $respostas = collect($inscricao->respostasParaExibicao())->keyBy('pergunta');

        $arquivo = $respostas['Relatório da Presidência'];

        $this->assertNotNull($arquivo['url'], 'não gerou link para o arquivo');
        $this->assertSame('Ver PDF enviado', $arquivo['rotulo']);
        // O caminho não pode sobrar como texto exibível.
        $this->assertSame('', $arquivo['texto']);
    }

    public function test_resposta_de_texto_continua_texto(): void
    {
        $respostas = collect($this->inscricaoComArquivo()->respostasParaExibicao())->keyBy('pergunta');

        $this->assertSame('Delegado', $respostas['Detalhe sua função no congresso']['texto']);
        $this->assertNull($respostas['Detalhe sua função no congresso']['url']);
    }

    public function test_rotulo_acompanha_o_tipo_do_arquivo(): void
    {
        // HEIC o navegador não abre: o rótulo precisa dizer "baixar", senão a
        // tesouraria clica e acha que o arquivo está corrompido.
        foreach ([
            'respostas/a.pdf' => 'Ver PDF enviado',
            'respostas/b.HEIC' => 'Baixar foto enviada (HEIC)',
            'respostas/c.jpg' => 'Ver arquivo enviado',
        ] as $caminho => $esperado) {
            $inscricao = $this->inscricaoComArquivo($caminho);

            $respostas = collect($inscricao->respostasParaExibicao())->keyBy('pergunta');

            $this->assertSame($esperado, $respostas['Relatório da Presidência']['rotulo'], "rótulo errado para {$caminho}");
        }
    }

    public function test_pergunta_renomeada_depois_nao_volta_a_mostrar_o_caminho(): void
    {
        /*
         * A resposta é indexada pelo texto da pergunta. Renomeada no evento,
         * não há definição para consultar o tipo — e sem o reforço pelo
         * prefixo do diretório o caminho voltaria a aparecer cru.
         */
        $inscricao = $this->inscricaoComArquivo();

        $inscricao->event->update(['custom_fields' => [
            ['question' => 'Relatório da Presidência (2026)', 'type' => 'file'],
        ]]);

        $respostas = collect($inscricao->fresh()->respostasParaExibicao())->keyBy('pergunta');

        $this->assertNotNull($respostas['Relatório da Presidência']['url']);
        $this->assertSame('Ver PDF enviado', $respostas['Relatório da Presidência']['rotulo']);
    }

    public function test_checkbox_sai_como_lista_separada_por_virgula(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Modalidades', 'type' => 'checkbox', 'options' => [
                ['label' => 'Futsal', 'surcharge' => 0],
                ['label' => 'Xadrez', 'surcharge' => 0],
            ]]],
        ]);

        $inscricao = Registration::factory()->create([
            'event_id' => $evento->id,
            'custom_answers' => ['Modalidades' => ['Futsal', 'Xadrez']],
        ]);

        $this->assertSame('Futsal, Xadrez', $inscricao->respostasParaExibicao()[0]['texto']);
    }

    // ------------------------------------------------------- nas telas

    /**
     * O caminho não pode aparecer como **texto**.
     *
     * `assertDontSee` cru não serve aqui: a URL assinada contém o caminho
     * (`.../respostas/abc123.pdf?expiration=…`), e é correto que contenha. O
     * que não pode é a pessoa ler `respostas/abc123.pdf` na tela — por isso a
     * verificação é sobre o texto, sem as tags.
     */
    private function assertCaminhoNaoVisivel(string $html): void
    {
        $texto = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('Ver PDF enviado', $texto);
        $this->assertStringNotContainsString('respostas/abc123.pdf', $texto);
    }

    public function test_minhas_inscricoes_mostra_o_link_e_nao_o_caminho(): void
    {
        $inscricao = $this->inscricaoComArquivo();

        $resposta = $this->actingAs($inscricao->user)->get('/dashboard')->assertSuccessful();

        $this->assertCaminhoNaoVisivel($resposta->getContent());
    }

    public function test_pagina_do_evento_mostra_o_link_e_nao_o_caminho(): void
    {
        $inscricao = $this->inscricaoComArquivo();

        $html = Livewire::actingAs($inscricao->user)
            ->test('event-show', ['id' => $inscricao->event_id])
            ->html();

        $this->assertCaminhoNaoVisivel($html);
    }

    public function test_painel_da_diretoria_mostra_o_link_e_nao_o_caminho(): void
    {
        // É quem mais precisa abrir: a conferência do documento acontece aqui.
        $inscricao = $this->inscricaoComArquivo();

        $resposta = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get("/area-da-diretoria/registrations/{$inscricao->id}/edit")
            ->assertSuccessful();

        $this->assertCaminhoNaoVisivel($resposta->getContent());
    }
}
