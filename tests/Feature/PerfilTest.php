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

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_grava_igreja_e_telefone(): void
    {
        $igreja = Church::factory()->create();
        $user = User::factory()->create(['church_id' => null, 'phone' => null]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Fulano de Tal',
                'email' => 'fulano@example.com',
                'church_id' => $igreja->id,
                'phone' => '(84) 99135-0289',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame($igreja->id, $user->church_id);
        // Guardado só com dígitos, igual ao formulário de inscrição.
        $this->assertSame('84991350289', $user->phone);
    }

    public function test_igreja_e_telefone_sao_opcionais(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Fulano de Tal',
                'email' => 'fulano@example.com',
                'church_id' => '',
                'phone' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->phone);
    }

    public function test_telefone_invalido_e_recusado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Fulano de Tal',
                'email' => 'fulano@example.com',
                'phone' => '123',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_igreja_de_outra_lista_e_recusada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Fulano de Tal',
                'email' => 'fulano@example.com',
                'church_id' => 99999,
            ])
            ->assertSessionHasErrors('church_id');
    }

    public function test_formulario_de_inscricao_vem_preenchido_com_os_dados_do_perfil(): void
    {
        $igreja = Church::factory()->create();
        $user = User::factory()->memberOfChurch($igreja)->create(['phone' => '84991350289']);
        $evento = Event::factory()->create();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->assertSet('name', $user->name)
            ->assertSet('email', $user->email)
            ->assertSet('church_id', $igreja->id)
            ->assertSet('phone', '84991350289');
    }

    public function test_inscricao_completa_o_perfil_que_estava_em_branco(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $igreja = Church::factory()->create();
        $user = User::factory()->create(['church_id' => null, 'phone' => null]);
        $evento = Event::factory()->create();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84991350289')
            ->set('church_id', $igreja->id)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('register')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame($igreja->id, $user->church_id);
        $this->assertSame('84991350289', $user->phone);
        // E não vira presidente de UMP por tabela.
        $this->assertFalse($user->is_church_president);
    }

    public function test_inscricao_nao_sobrescreve_o_que_ja_estava_no_perfil(): void
    {
        Storage::fake(config('femopror.uploads.disk'));

        $minhaIgreja = Church::factory()->create();
        $outraIgreja = Church::factory()->create();
        $user = User::factory()->memberOfChurch($minhaIgreja)->create(['phone' => '84900000000']);
        $evento = Event::factory()->create();

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84911111111')
            ->set('church_id', $outraIgreja->id)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('register')
            ->assertHasNoErrors();

        $user->refresh();

        // O cadastro da pessoa não muda por causa de uma inscrição avulsa...
        $this->assertSame($minhaIgreja->id, $user->church_id);
        $this->assertSame('84900000000', $user->phone);

        // ...mas a inscrição guarda o que ela informou naquele evento.
        $inscricao = Registration::sole();
        $this->assertSame($outraIgreja->id, $inscricao->church_id);
        $this->assertSame('84911111111', $inscricao->phone);
    }

    public function test_pagina_do_perfil_lista_as_igrejas(): void
    {
        $igreja = Church::factory()->create(['name' => 'Igreja Presbiteriana do Planalto']);

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertSee($igreja->name)
            ->assertSee('WhatsApp');
    }
}
