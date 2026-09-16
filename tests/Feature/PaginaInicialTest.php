<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Church;
use App\Models\Download;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginaInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_responde_na_raiz(): void
    {
        $this->get('/')->assertSuccessful();
    }

    public function test_home_mostra_evento_publicado_e_esconde_rascunho(): void
    {
        Event::factory()->create(['title' => 'Congresso Aberto']);
        Event::factory()->draft()->create(['title' => 'Evento Ainda Secreto']);

        $this->get('/')
            ->assertSee('Congresso Aberto')
            ->assertDontSee('Evento Ainda Secreto');
    }

    public function test_icone_invalido_no_banco_nao_derruba_a_home(): void
    {
        // @svg($file->icon) com um nome que não existe levantava exceção e
        // levava a página inteira junto.
        Download::create([
            'title' => 'Manual da UMP',
            'file_path' => 'downloads-publicos/manual.pdf',
            'icon' => 'heroicon-o-isso-nao-existe',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('Manual da UMP');
    }

    public function test_home_lista_somente_a_diretoria_ativa(): void
    {
        $igreja = Church::factory()->create();

        Board::factory()->inactive()->create([
            'church_id' => $igreja->id,
            'president_name' => 'Presidente Antigo',
        ]);

        Board::factory()->create([
            'church_id' => $igreja->id,
            'president_name' => 'Presidente Atual',
        ]);

        $this->get('/')
            ->assertSee('Presidente Atual')
            ->assertDontSee('Presidente Antigo');
    }

    public function test_marcar_diretoria_como_ativa_desativa_a_anterior(): void
    {
        $igreja = Church::factory()->create();

        $antiga = Board::factory()->create(['church_id' => $igreja->id]);
        $nova = Board::factory()->create(['church_id' => $igreja->id]);

        $this->assertFalse($antiga->fresh()->is_active);
        $this->assertTrue($nova->fresh()->is_active);
    }

    public function test_igrejas_saem_ordenadas_ignorando_o_prefixo(): void
    {
        Church::factory()->create(['name' => 'Igreja Presbiteriana do Planalto']);
        Church::factory()->create(['name' => 'Igreja Presbiteriana das Barrocas']);
        Church::factory()->create(['name' => 'Igreja Presbiteriana de Assú']);

        $resposta = $this->get('/')->getContent();

        $assu = strpos($resposta, 'Assú');
        $barrocas = strpos($resposta, 'Barrocas');
        $planalto = strpos($resposta, 'Planalto');

        $this->assertTrue($assu < $barrocas && $barrocas < $planalto);
    }

    public function test_pagina_do_evento_traz_meta_de_compartilhamento(): void
    {
        // Sem isto, o link colado no WhatsApp aparece sem título e sem imagem.
        $evento = Event::factory()->create(['title' => 'Congresso 2026']);

        $this->get(route('events.show', $evento->id))
            ->assertSuccessful()
            ->assertSee('og:title', escape: false)
            ->assertSee('Congresso 2026');
    }
}
