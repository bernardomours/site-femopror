<?php

namespace Tests\Feature;

use App\Console\Commands\MigrarPerguntas;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\PanoramaDoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O construtor de formulário: formato das opções, tipos novos e o conversor.
 */
class ConstrutorDeFormularioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
    }

    private function inscrever(Event $evento, array $respostas = []): \Livewire\Features\SupportTesting\Testable
    {
        $componente = Livewire::actingAs(User::factory()->create())
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

        $this->fail("pergunta com [{$trecho}] não existe");
    }

    // ------------------------------------------- os dois formatos

    public function test_le_o_formato_antigo_em_string(): void
    {
        /*
         * O conversor roda à mão, com backup. Entre o deploy e a conversão o
         * banco tem os dois formatos convivendo — ler só o novo deixaria todo
         * evento existente sem opção nenhuma, ou seja, sem validação e sem
         * preço.
         */
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Modalidade', 'type' => 'checkbox', 'options' => 'Futsal(R$5,00), Xadrez'],
            ],
        ]);

        $opcoes = $evento->customFieldDefinitions()[0]['options'];

        $this->assertSame('Futsal(R$5,00)', $opcoes[0]['label']);
        $this->assertSame(5.0, $opcoes[0]['surcharge']);
        $this->assertSame(0.0, $opcoes[1]['surcharge']);
    }

    public function test_le_o_formato_novo_estruturado(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Modalidade', 'type' => 'checkbox', 'options' => [
                    ['label' => 'Futsal', 'surcharge' => 5],
                    ['label' => 'Xadrez', 'surcharge' => 0],
                ]],
            ],
        ]);

        $opcoes = $evento->customFieldDefinitions()[0]['options'];

        $this->assertSame('Futsal', $opcoes[0]['label']);
        $this->assertSame(5.0, $opcoes[0]['surcharge']);
    }

    public function test_preco_sai_igual_nos_dois_formatos(): void
    {
        // A conversão não pode mudar quanto a pessoa paga.
        foreach ([
            'Futsal(R$5,00), Vôlei(R$5,00)',
            [['label' => 'Futsal', 'surcharge' => 5], ['label' => 'Vôlei', 'surcharge' => 5]],
        ] as $opcoes) {
            $evento = Event::factory()->create([
                'price' => 70,
                'custom_fields' => [['question' => 'Modalidade', 'type' => 'checkbox', 'options' => $opcoes]],
            ]);

            $rotulo = $evento->customFieldDefinitions()[0]['options'][0]['label'];

            $this->inscrever($evento, [$this->chaveDe($evento, 'Modalidade') => [$rotulo]])
                ->assertHasNoErrors();

            $this->assertSame('75.00', Registration::latest('id')->first()->amount_paid);
        }
    }

    // ------------------------------------------------- o conversor

    public function test_conversor_limpa_o_rotulo_e_a_resposta_ja_gravada(): void
    {
        /*
         * A resposta guarda o rótulo por extenso. Se o conversor limpasse só a
         * pergunta, "Futsal(R$5,00)" deixaria de casar com a opção "Futsal" e
         * o gráfico do painel racharia em duas colunas para a mesma coisa.
         */
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Modalidade', 'type' => 'checkbox', 'options' => 'Futsal(R$5,00), Xadrez (+2,00)'],
            ],
        ]);

        Registration::factory()->create([
            'event_id' => $evento->id,
            'custom_answers' => ['Modalidade' => ['Futsal(R$5,00)']],
        ]);

        $this->artisan('femopror:migrar-perguntas')
            ->expectsConfirmation('Você fez backup do banco?', 'yes')
            ->assertSuccessful();

        $opcoes = $evento->fresh()->customFieldDefinitions()[0]['options'];

        $this->assertSame('Futsal', $opcoes[0]['label']);
        $this->assertSame(5.0, $opcoes[0]['surcharge']);
        $this->assertSame('Xadrez', $opcoes[1]['label']);

        $this->assertSame(['Futsal'], Registration::sole()->custom_answers['Modalidade']);
    }

    public function test_dry_run_nao_grava_nada(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Modalidade', 'type' => 'select', 'options' => 'Futsal(R$5,00)']],
        ]);

        $antes = $evento->custom_fields;

        $this->artisan('femopror:migrar-perguntas --dry-run')->assertSuccessful();

        $this->assertSame($antes, $evento->fresh()->custom_fields);
    }

    public function test_recusar_o_backup_aborta(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Modalidade', 'type' => 'select', 'options' => 'Futsal(R$5,00)']],
        ]);

        $antes = $evento->custom_fields;

        $this->artisan('femopror:migrar-perguntas')
            ->expectsConfirmation('Você fez backup do banco?', 'no')
            ->assertFailed();

        $this->assertSame($antes, $evento->fresh()->custom_fields);
    }

    public function test_parenteses_sem_numero_nao_e_tocado(): void
    {
        // Parêntese sem dígito nunca foi acréscimo — não pode virar corte.
        $this->assertSame(
            'Diretoria ou secretário (FEMOPROR)',
            MigrarPerguntas::limparRotulo('Diretoria ou secretário (FEMOPROR)'),
        );

        $this->assertSame('Futsal', MigrarPerguntas::limparRotulo('Futsal(R$5,00)'));
        $this->assertSame('M', MigrarPerguntas::limparRotulo('M (+30,00)'));
        // Rótulo que é só o parêntese não pode virar string vazia.
        $this->assertSame('(2)', MigrarPerguntas::limparRotulo('(2)'));
    }

    public function test_rodar_duas_vezes_nao_estraga_nada(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Modalidade', 'type' => 'select', 'options' => 'Futsal(R$5,00)']],
        ]);

        $this->artisan('femopror:migrar-perguntas')->expectsConfirmation('Você fez backup do banco?', 'yes');
        $depoisDaPrimeira = $evento->fresh()->custom_fields;

        $this->artisan('femopror:migrar-perguntas')->assertSuccessful();

        $this->assertSame($depoisDaPrimeira, $evento->fresh()->custom_fields);
    }

    // ------------------------------------------------- tipos novos

    public function test_escolha_unica_valida_contra_as_opcoes(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [[
                'question' => 'Categoria', 'type' => 'radio', 'required' => true,
                'options' => [['label' => 'Delegado', 'surcharge' => 0], ['label' => 'Visitante', 'surcharge' => 0]],
            ]],
        ]);

        $chave = $this->chaveDe($evento, 'Categoria');

        $this->inscrever($evento, [$chave => 'Presidente'])->assertHasErrors("respostas.{$chave}");
        $this->assertSame(0, Registration::count());

        $this->inscrever($evento, [$chave => 'Delegado'])->assertHasNoErrors();
        $this->assertSame('Delegado', Registration::sole()->custom_answers['Categoria']);
    }

    public function test_texto_longo_aceita_mais_que_o_texto_curto(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Conte sobre você', 'type' => 'textarea', 'options' => []]],
        ]);

        $this->inscrever($evento, [$this->chaveDe($evento, 'Conte') => str_repeat('a', 1500)])
            ->assertHasNoErrors();

        $this->assertSame(1500, strlen(Registration::sole()->custom_answers['Conte sobre você']));
    }

    public function test_arquivo_vai_para_disco_privado_e_grava_o_caminho(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Autorização dos pais', 'type' => 'file', 'required' => true, 'options' => []]],
        ]);

        $chave = $this->chaveDe($evento, 'Autorização');

        $this->inscrever($evento, [$chave => UploadedFile::fake()->create('autorizacao.pdf', 200, 'application/pdf')])
            ->assertHasNoErrors();

        $caminho = Registration::sole()->custom_answers['Autorização dos pais'];

        $this->assertStringStartsWith('respostas/', $caminho);
        // Documento pessoal mora no mesmo disco privado do comprovante.
        Storage::disk(config('femopror.uploads.disk'))->assertExists($caminho);
    }

    public function test_arquivo_obrigatorio_em_branco_barra(): void
    {
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Autorização dos pais', 'type' => 'file', 'required' => true, 'options' => []]],
        ]);

        $this->inscrever($evento)->assertHasErrors('respostas.'.$this->chaveDe($evento, 'Autorização'));
        $this->assertSame(0, Registration::count());
    }

    public function test_voltar_para_alterar_dados_nao_apaga_o_arquivo(): void
    {
        /*
         * Depois de gravado, o que resta na propriedade é o **caminho**, não o
         * arquivo. Aplicar a regra `file` nele reprovaria quem só voltou para
         * corrigir o telefone — e guardar a string vazia apagaria o anexo.
         */
        $evento = Event::factory()->create([
            'custom_fields' => [['question' => 'Autorização dos pais', 'type' => 'file', 'required' => true, 'options' => []]],
        ]);

        $user = User::factory()->create();
        $chave = $this->chaveDe($evento, 'Autorização');

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->set("respostas.{$chave}", UploadedFile::fake()->create('autorizacao.pdf', 200, 'application/pdf'))
            ->call('salvarDados')
            ->assertHasNoErrors();

        $caminho = Registration::sole()->custom_answers['Autorização dos pais'];

        Livewire::actingAs($user->fresh())
            ->test('event-show', ['id' => $evento->id])
            ->call('alterarDados')
            ->set('phone', '84988887777')
            ->call('salvarDados')
            ->assertHasNoErrors();

        $this->assertSame($caminho, Registration::sole()->custom_answers['Autorização dos pais']);
    }

    public function test_pergunta_de_arquivo_nao_vira_grafico(): void
    {
        // Seria uma coluna de altura 1 por inscrito, com o caminho de um
        // documento pessoal no eixo de uma tela que é só de números.
        $evento = Event::factory()->create([
            'custom_fields' => [
                ['question' => 'Autorização dos pais', 'type' => 'file', 'options' => []],
                ['question' => 'Categoria', 'type' => 'radio', 'options' => [['label' => 'Delegado', 'surcharge' => 0]]],
            ],
        ]);

        Registration::factory()->create([
            'event_id' => $evento->id,
            'church_id' => Church::factory()->create()->id,
            'custom_answers' => ['Autorização dos pais' => 'respostas/abc.pdf', 'Categoria' => 'Delegado'],
        ]);

        $titulos = collect(PanoramaDoEvento::para($evento)->graficos())->pluck('titulo');

        $this->assertContains('Categoria', $titulos);
        $this->assertNotContains('Autorização dos pais', $titulos);
    }
}
