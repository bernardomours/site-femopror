<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'event_date',
        'opening_date',
        'location',
        'price',
        'requires_receipt',
        'custom_fields',
        'form_pages',
        'status',
        'is_congress',
        'image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_date' => 'datetime',
            'opening_date' => 'datetime',
            'price' => 'decimal:2',
            'requires_receipt' => 'boolean',
            'is_congress' => 'boolean',
            'custom_fields' => 'array',
            'form_pages' => 'array',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function congressSubscriptions(): HasMany
    {
        return $this->hasMany(CongressSubscription::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Já abriu a janela de inscrição? `opening_date` em branco significa "abre
     * junto com a publicação".
     */
    public function registrationHasOpened(): bool
    {
        return $this->opening_date === null || now()->greaterThanOrEqualTo($this->opening_date);
    }

    /**
     * A pergunta que toda tela de inscrição precisa fazer antes de gravar.
     * Rascunho e encerrado não aceitam inscrição — nem pela URL direta.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status === 'published' && $this->registrationHasOpened();
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /**
     * Comprovante só é exigido quando a inscrição custa alguma coisa. Evento
     * gratuito pedindo PIX era um beco sem saída no formulário.
     *
     * A pergunta é sobre o valor DA INSCRIÇÃO, não o preço base do evento: um
     * evento de base gratuita pode cobrar pelas modalidades escolhidas, e aí
     * o comprovante passa a valer.
     */
    public function requiresReceiptFor(float $valor): bool
    {
        return $this->requires_receipt && $valor > 0;
    }

    /**
     * Os tipos de pergunta que o construtor oferece.
     *
     * `file` guarda o caminho do arquivo como resposta; os demais guardam
     * texto (ou lista de textos, no caso do checkbox).
     *
     * @return array<string, string>
     */
    public static function tiposDePergunta(): array
    {
        return [
            'text' => 'Texto curto',
            'textarea' => 'Texto longo',
            'select' => 'Lista suspensa',
            'radio' => 'Escolha única',
            'checkbox' => 'Múltipla escolha',
            'file' => 'Envio de arquivo',
        ];
    }

    /** Tipos que têm lista de opções — e, portanto, podem cobrar a mais. */
    public static function tiposComOpcoes(): array
    {
        return ['select', 'radio', 'checkbox'];
    }

    /**
     * As páginas do formulário, já normalizadas.
     *
     * A primeira é sempre **Seus dados**, e não vem do cadastro: nome, e-mail,
     * WhatsApp e igreja existem em todo evento, e deixar a diretoria removê-la
     * produziria um formulário sem identificação de ninguém.
     *
     * Sem páginas cadastradas, é essa página única — que é exatamente o
     * formulário de hoje, de uma tela só.
     *
     * @return array<int, array{title: string, description: string}>
     */
    public function formPages(): array
    {
        $paginas = [[
            'title' => 'Seus dados',
            'description' => '',
        ]];

        foreach ($this->form_pages ?? [] as $pagina) {
            $titulo = trim((string) ($pagina['title'] ?? ''));

            if ($titulo === '') {
                continue;
            }

            $paginas[] = [
                'title' => $titulo,
                'description' => trim((string) ($pagina['description'] ?? '')),
            ];
        }

        return $paginas;
    }

    /**
     * Normaliza `custom_fields` para uso na tela e no cálculo do preço.
     *
     * Cada pergunta ganha uma chave estável derivada do texto (`key`), usada
     * para indexar as respostas no formulário. As opções voltam já separadas
     * em rótulo e acréscimo, para que o preço não precise ser reextraído por
     * regex do que o cliente mandou de volta.
     *
     * @return array<int, array{key: string, question: string, type: string, help: string, image_path: ?string, required: bool, options: array<int, array{label: string, surcharge: float}>}>
     */
    public function customFieldDefinitions(): array
    {
        $definicoes = [];

        // Fora do laço: não muda de pergunta para pergunta.
        $paginas = $this->formPages();

        foreach ($this->custom_fields ?? [] as $indice => $campo) {
            $pergunta = trim((string) ($campo['question'] ?? ''));

            if ($pergunta === '') {
                continue;
            }

            $tipo = $campo['type'] ?? 'text';

            if (! array_key_exists($tipo, self::tiposDePergunta())) {
                $tipo = 'text';
            }

            $definicoes[] = [
                'key' => $indice.'-'.(Str::slug($pergunta) ?: 'campo'),
                'question' => $pergunta,
                'type' => $tipo,
                // Texto de apoio abaixo do campo ("confira o DDD", "formato
                // PDF"). Resolve metade das dúvidas antes de virarem mensagem.
                'help' => trim((string) ($campo['help'] ?? '')),
                'image_path' => filled($campo['image_path'] ?? null) ? (string) $campo['image_path'] : null,
                'options' => in_array($tipo, self::tiposComOpcoes(), true)
                    ? self::normalizeOptions($campo['options'] ?? [])
                    : [],
                /*
                 * Em que página a pergunta aparece. Zero é "Seus dados", que é
                 * onde cai tudo que não escolheu página — inclusive todo evento
                 * anterior a esta coluna.
                 *
                 * Apontar para uma página que não existe mais (a diretoria
                 * apagou a página e esqueceu a pergunta) também cai na
                 * primeira: some do lugar errado, mas não some do formulário.
                 * Pergunta invisível é resposta que nunca chega, e ninguém
                 * descobre até o dia do evento.
                 */
                'page' => array_key_exists($pagina = (int) ($campo['page'] ?? 0), $paginas) ? $pagina : 0,
                /*
                 * Toda pergunta personalizada era opcional, sem como mudar.
                 * Serve para "qual o tamanho da sua camisa?", mas não para
                 * "possui alguma restrição alimentar?" — essa a cozinha precisa
                 * saber antes, e uma resposta em branco só aparece no dia.
                 *
                 * Ausente significa opcional: evento já cadastrado não muda de
                 * comportamento por causa desta coluna nova no JSON.
                 */
                'required' => (bool) ($campo['required'] ?? false),
            ];
        }

        return $definicoes;
    }

    /**
     * "Camisa P (+30,00), Camisa M (+30)" vira rótulo + acréscimo.
     * O acréscimo sai daqui e só daqui: nunca do que o navegador devolveu.
     *
     * A separação NÃO é um explode(',') simples: a vírgula decimal do preço
     * mora dentro dos parênteses. Com o explode, "M (+30,00)" virava duas
     * opções — "M (+30" e "00)" — e o acréscimo sumia. Quem escrevesse o valor
     * do jeito brasileiro perdia os 30 reais sem nenhum aviso.
     *
     * @return array<int, array{label: string, surcharge: float}>
     */
    /**
     * Devolve as opções em `{label, surcharge}`, venha o que vier.
     *
     * O formato gravado mudou: era **uma string** com as opções separadas por
     * vírgula e o preço escrito dentro de parênteses (`Futsal(R$5,00)`); hoje é
     * uma lista de `{label, surcharge}`, com rótulo e valor em campos próprios.
     *
     * Os dois continuam sendo lidos aqui, e não por falta de decisão: o
     * conversor (`femopror:migrar-perguntas`) é rodado à mão, com backup, então
     * entre o deploy e a conversão o banco tem os dois formatos convivendo. Ler
     * só o novo deixaria todo evento existente sem opção nenhuma — ou seja, sem
     * validação e sem preço — até alguém lembrar de rodar o comando.
     *
     * @param  array<int, mixed>|string  $bruto
     * @return array<int, array{label: string, surcharge: float}>
     */
    public static function normalizeOptions(array | string $bruto): array
    {
        if (is_string($bruto)) {
            return self::parseOptions($bruto);
        }

        $opcoes = [];

        foreach ($bruto as $opcao) {
            // Lista de strings simples também é aceita: é o que sai de um
            // modelo escrito à mão sem valor nenhum.
            if (is_string($opcao)) {
                $opcao = ['label' => $opcao];
            }

            if (! is_array($opcao)) {
                continue;
            }

            $rotulo = trim((string) ($opcao['label'] ?? ''));

            if ($rotulo === '') {
                continue;
            }

            $opcoes[] = [
                'label' => $rotulo,
                'surcharge' => round((float) ($opcao['surcharge'] ?? 0), 2),
            ];
        }

        return $opcoes;
    }

    /**
     * Lê o formato ANTIGO: string separada por vírgula, com o acréscimo dentro
     * de parênteses. Continua aqui por causa do conversor e dos eventos ainda
     * não convertidos — não use em código novo.
     */
    public static function parseOptions(string $bruto): array
    {
        $opcoes = [];

        // Vírgula que não está dentro de parênteses.
        $pedacos = preg_split('/,(?![^(]*\))/', $bruto) ?: [];

        foreach ($pedacos as $pedaco) {
            $rotulo = trim($pedaco);

            if ($rotulo === '') {
                continue;
            }

            $acrescimo = 0.0;

            if (preg_match('/\(\+?[^0-9]*(\d+(?:[.,]\d+)?)[^)]*\)/', $rotulo, $captura)) {
                $acrescimo = (float) str_replace(',', '.', $captura[1]);
            }

            $opcoes[] = ['label' => $rotulo, 'surcharge' => $acrescimo];
        }

        return $opcoes;
    }
}
