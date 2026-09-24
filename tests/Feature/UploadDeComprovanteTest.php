<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Support\UploadLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O upload é o ponto onde a inscrição morre em silêncio: quando falha, a pessoa
 * só vê "escolhi o arquivo e não aconteceu nada".
 */
class UploadDeComprovanteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('femopror.uploads.disk'));
    }

    private function inscrever(User $user, Event $evento): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->set('name', 'Fulano de Tal')
            ->set('email', 'fulano@example.com')
            ->set('phone', '84999999999')
            ->set('church_id', Church::factory()->create()->id)
            ->call('salvarDados');
    }

    public function test_arquivo_em_transito_nunca_vai_direto_para_o_bucket(): void
    {
        /*
         * Esta é a regressão que derrubou o upload em produção.
         *
         * O Livewire decide a estratégia olhando `filesystems.default`: se for
         * um disco `s3`, ele manda o navegador enviar DIRETO para o bucket, por
         * URL pré-assinada — o que só funciona com CORS configurado no R2. Sem
         * CORS, o navegador bloqueia e nada acontece na tela.
         *
         * O disco temporário fica fixado em `local` justamente para essa
         * decisão não depender de como o .env de produção está configurado.
         */
        config(['filesystems.default' => 's3']);

        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $this->assertFalse(
            FileUploadConfiguration::isUsingS3(),
            'o upload temporário voltou a ir direto para o bucket: vai falhar sem CORS',
        );
    }

    public function test_upload_funciona_mesmo_com_o_disco_padrao_apontando_para_o_bucket(): void
    {
        // Mesma configuração de produção, ponta a ponta.
        config(['filesystems.default' => 's3']);

        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $this->inscrever($user, $evento)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $this->assertNotNull(Registration::sole()->receipt_path);
    }

    public function test_foto_de_iphone_em_heic_e_aceita(): void
    {
        // A câmera do iPhone grava em HEIC por padrão. Com a regra só de
        // jpg/png/pdf, boa parte do público não conseguia mandar a própria foto.
        $evento = Event::factory()->create();

        $this->inscrever(User::factory()->create(), $evento)
            ->set('receipt', UploadedFile::fake()->create('foto.heic', 300, 'image/heic'))
            ->call('enviarComprovante')
            ->assertHasNoErrors();

        $this->assertStringEndsWith('.heic', Registration::sole()->receipt_path);
    }

    public function test_arquivo_acima_do_limite_do_servidor_e_recusado_com_mensagem(): void
    {
        $evento = Event::factory()->create();
        $acimaDoLimite = UploadLimit::maxKilobytes() + 512;

        $this->inscrever(User::factory()->create(), $evento)
            ->set('receipt', UploadedFile::fake()->create('grande.jpg', $acimaDoLimite, 'image/jpeg'))
            ->call('enviarComprovante')
            ->assertHasErrors(['receipt' => 'max']);

        $this->assertNull(Registration::sole()->receipt_path);
    }

    public function test_a_tela_avisa_quando_o_upload_falha(): void
    {
        // O Livewire dispara `livewire-upload-error` quando o envio quebra.
        // Ninguém escutava, e a falha virava silêncio: o sintoma relatado.
        $evento = Event::factory()->create();

        $this->inscrever(User::factory()->create(), $evento)
            ->assertSeeHtml('livewire-upload-error')
            ->assertSeeHtml('livewire-upload-progress');
    }

    public function test_campo_aceita_foto_e_pdf_no_seletor_de_arquivos(): void
    {
        $evento = Event::factory()->create();

        $html = $this->inscrever(User::factory()->create(), $evento)->html();

        foreach (['image/jpeg', 'image/png', 'image/heic', 'application/pdf'] as $tipo) {
            $this->assertStringContainsString($tipo, $html, "o seletor não oferece [{$tipo}]");
        }
    }

    // ------------------------------------------- inscrição duplicada

    public function test_quem_ja_esta_inscrito_ve_o_status_e_o_caminho_para_os_detalhes(): void
    {
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $this->inscrever($user, $evento)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('enviarComprovante');

        // Voltando ao evento, a tela diz em que pé está — não só "já inscrito".
        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->assertSee('Você está inscrito!')
            ->assertSee('Comprovante em análise')
            ->assertSee('Veja mais detalhes em Minhas inscrições')
            ->assertSeeHtml(route('dashboard'))
            // E o formulário de inscrição não reaparece.
            ->assertDontSee('Ir para pagamento');
    }

    public function test_status_exibido_acompanha_a_confirmacao_da_tesouraria(): void
    {
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $this->inscrever($user, $evento)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('enviarComprovante');

        Registration::sole()->update(['payment_status' => 'paid']);

        Livewire::actingAs($user)
            ->test('event-show', ['id' => $evento->id])
            ->assertSee('Pagamento confirmado');
    }

    public function test_banco_impede_segunda_inscricao_no_mesmo_evento(): void
    {
        // A mensagem é a explicação; a garantia é o índice único.
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        Registration::factory()->create(['event_id' => $evento->id, 'user_id' => $user->id]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Registration::factory()->create(['event_id' => $evento->id, 'user_id' => $user->id]);
    }

    public function test_heic_e_sinalizado_para_quem_confere(): void
    {
        // O arquivo é aceito, mas o navegador não exibe HEIC: a tesouraria
        // precisa saber que tem que baixar, em vez de achar que está corrompido.
        $heic = Registration::factory()->create(['receipt_path' => 'receipts/foto.HEIC']);
        $jpg = Registration::factory()->create(['receipt_path' => 'receipts/print.jpg']);

        $this->assertTrue($heic->receiptIsHeic());
        $this->assertFalse($jpg->receiptIsHeic());
        $this->assertFalse($heic->receiptIsPdf());
    }

    public function test_tela_avisa_sobre_formato_do_iphone_antes_de_enviar(): void
    {
        $evento = Event::factory()->create();

        $this->inscrever(User::factory()->create(), $evento)
            ->assertSeeHtml('heic|heif');
    }

    // ------------------------------------------- estrutura do formulário

    /**
     * Botão de enviar que ficou fora de um `<form>`.
     *
     * Um `<button type="submit">` só dispara `wire:submit` se estiver dentro do
     * formulário que tem a diretiva. Fora dele o clique não faz absolutamente
     * nada: nenhuma request sai, nenhuma validação roda, nenhuma mensagem
     * aparece — o mesmo sintoma de "seleciono e não acontece nada", agora no
     * botão.
     *
     * Nenhum teste pegava isso porque `Livewire::test()` chama os métodos
     * direto, sem passar pelo DOM: o componente respondia certinho enquanto a
     * tela real estava quebrada. Por isso este teste olha o HTML, não o estado.
     *
     * @return list<string> rótulos dos botões de envio que estão soltos
     */
    /**
     * Carrega o HTML para inspeção.
     *
     * O `@click` do Alpine precisa virar outro nome antes: `@` não é começo
     * válido de nome de atributo para o libxml, que então erra a leitura da tag
     * e deixa o `>` de uma arrow function (`() =>`) fechá-la no meio. O
     * resultado é o parser jurando que o código vazou quando a página está
     * correta — um falso positivo que já custou uma investigação.
     */
    private function documento(string $html): \DOMDocument
    {
        $html = preg_replace('/(\s)@([a-zA-Z][\w.:-]*)=/', '$1data-alpine-$2=', $html);

        $doc = new \DOMDocument;

        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $doc;
    }

    private function botoesForaDeFormulario(string $html): array
    {
        $doc = $this->documento($html);

        $soltos = [];

        foreach ((new \DOMXPath($doc))->query('//button[@type="submit"]') as $botao) {
            $dentroDeForm = false;

            for ($pai = $botao->parentNode; $pai !== null; $pai = $pai->parentNode) {
                if ($pai->nodeName === 'form') {
                    $dentroDeForm = true;
                    break;
                }
            }

            if (! $dentroDeForm) {
                $soltos[] = trim(preg_replace('/\s+/', ' ', $botao->textContent));
            }
        }

        return $soltos;
    }

    public function test_botao_de_enviar_comprovante_esta_dentro_do_formulario(): void
    {
        $evento = Event::factory()->create();

        $html = $this->inscrever(User::factory()->create(), $evento)->html();

        $this->assertStringContainsString(
            'wire:submit.prevent="enviarComprovante"',
            $html,
            'o formulário do comprovante perdeu a diretiva de envio',
        );

        $this->assertSame(
            [],
            $this->botoesForaDeFormulario($html),
            'botão de envio fora de <form>: o clique não dispara nada',
        );
    }

    /**
     * Texto que o participante realmente lê na tela — sem atributos, sem
     * `<script>`. É nisso que um atributo Alpine quebrado aparece.
     */
    private function textoVisivel(string $html): string
    {
        $doc = $this->documento($html);

        $xpath = new \DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//script | //style')) as $no) {
            $no->parentNode?->removeChild($no);
        }

        return (string) $doc->textContent;
    }

    public function test_codigo_do_alpine_nao_vaza_como_texto_na_tela(): void
    {
        /*
         * O bloco `x-data="{ ... }"` é o valor de um atributo delimitado por
         * aspas duplas. Uma única aspa dupla lá dentro — inclusive dentro de um
         * comentário — encerra o atributo mais cedo, e todo o resto do
         * JavaScript é renderizado como texto no meio da página, por cima do
         * QR Code do PIX. Aconteceu exatamente assim.
         *
         * A suíte inteira passava com a tela nesse estado: os testes conferiam
         * que certos trechos existem no HTML, e eles continuavam existindo —
         * só que como texto, não como comportamento. Por isso este teste olha o
         * texto visível, que é o que denuncia o vazamento.
         */
        $evento = Event::factory()->create();

        $texto = $this->textoVisivel(
            $this->inscrever(User::factory()->create(), $evento)->html()
        );

        foreach (['this.erro', 'this.enviando', 'setTimeout(', 'clearTimeout(', '=>', 'evento.target.files'] as $fragmento) {
            $this->assertStringNotContainsString(
                $fragmento,
                $texto,
                "código do Alpine vazou como texto na tela: [{$fragmento}]. ".
                'Quase sempre é uma aspa dupla dentro de um atributo x-data/x-on.',
            );
        }
    }

    public function test_botao_de_trocar_comprovante_esta_dentro_do_formulario(): void
    {
        // A troca é um segundo formulário na mesma tela — e quebra igual.
        $evento = Event::factory()->create();
        $user = User::factory()->create();

        $componente = $this->inscrever($user, $evento)
            ->set('receipt', UploadedFile::fake()->image('pix.jpg'))
            ->call('enviarComprovante')
            ->call('trocarComprovante');

        $this->assertSame([], $this->botoesForaDeFormulario($componente->html()));
    }
}
