<?php

namespace App\Filament\Schemas;

use App\Models\Event;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * O construtor de perguntas, em um lugar só.
 *
 * Usado pelo cadastro de evento (`events.custom_fields`) e pelos modelos de
 * formulário (`form_templates.fields`). As duas telas guardam **a mesma
 * estrutura**, e é isso que permite carregar um modelo copiando o JSON, sem
 * conversão no meio. Duas cópias deste repeater divergiriam no primeiro campo
 * novo, e aí o modelo passaria a gerar pergunta que o evento não sabe ler.
 */
class ConstrutorDePerguntas
{
    /**
     * O repeater das páginas do formulário.
     *
     * "Seus dados" não está aqui e não é removível: nome, e-mail, WhatsApp e
     * igreja existem em todo evento. O que se cadastra são as páginas **depois**
     * dela.
     */
    public static function paginas(string $nome = 'form_pages'): Repeater
    {
        return Repeater::make($nome)
            ->label('Páginas do formulário')
            ->helperText('A primeira página é sempre "Seus dados" (nome, e-mail, WhatsApp e igreja). Adicione aqui as páginas seguintes — por exemplo "Informações adicionais" e "Documentos". Sem nenhuma, o formulário fica em uma tela só.')
            ->addActionLabel('Adicionar página')
            ->schema([
                TextInput::make('title')
                    ->label('Título da página')
                    ->placeholder('Ex: Informações adicionais')
                    ->required()
                    ->maxLength(255)
                    ->distinct()
                    ->validationMessages(['distinct' => 'Já existe outra página com este título.'])
                    ->live(onBlur: true),

                TextInput::make('description')
                    ->label('Explicação')
                    ->placeholder('Opcional — aparece abaixo do título, para quem se inscreve')
                    ->maxLength(255),
            ])
            ->columns(2)
            ->columnSpanFull()
            ->reorderableWithButtons()
            ->itemLabel(fn (array $state) => $state['title'] ?? 'Nova página');
    }

    /**
     * Rótulos das páginas para o seletor da pergunta: a fixa mais as
     * cadastradas, com o número que a pessoa vê na tela.
     *
     * @param  array<int|string, mixed>  $cadastradas
     * @return array<int, string>
     */
    private static function opcoesDePagina(array $cadastradas): array
    {
        $opcoes = [0 => '1. Seus dados'];

        foreach (array_values($cadastradas) as $i => $pagina) {
            $titulo = trim((string) ($pagina['title'] ?? ''));
            $numero = $i + 2;

            $opcoes[$i + 1] = $numero.'. '.($titulo !== '' ? $titulo : 'Sem título');
        }

        return $opcoes;
    }

    /**
     * @param  string  $paginas  caminho, relativo ao item do repeater, até a lista
     *                           de páginas. Muda conforme o host: no evento é
     *                           `form_pages`, no modelo é `pages`.
     */
    public static function repeater(string $nome, string $paginas = 'form_pages'): Repeater
    {
        $caminhoDasPaginas = '../../'.$paginas;

        return Repeater::make($nome)
            ->label('Perguntas do formulário')
            ->addActionLabel('Adicionar pergunta')
            ->schema([
                TextInput::make('question')
                    ->label('Pergunta')
                    ->placeholder('Ex: Qual o tamanho da sua camisa?')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    /*
                     * Duas perguntas com o mesmo texto se sobrescrevem: a
                     * resposta é gravada com o texto da pergunta como chave
                     * (`$saida[$campo['question']]`), então a segunda apaga a
                     * primeira **em silêncio**. Era improvável enquanto o
                     * cadastro era manual e penoso; com o construtor, vira
                     * questão de tempo.
                     */
                    ->distinct()
                    ->validationMessages([
                        'distinct' => 'Já existe outra pergunta com este texto. Duas perguntas iguais se sobrescrevem na hora de gravar a resposta.',
                    ])
                    ->columnSpan(2),

                Select::make('type')
                    ->label('Tipo de resposta')
                    ->options(Event::tiposDePergunta())
                    ->default('text')
                    ->required()
                    ->native(false)
                    ->live(),

                /*
                 * Em que página a pergunta aparece. As opções saem do que está
                 * cadastrado **agora** no repeater de páginas (`$get` sobe até
                 * a raiz com `../`), então criar uma página já a deixa
                 * escolhível aqui, sem salvar antes.
                 *
                 * Só aparece quando existe mais de uma página: num formulário
                 * de tela única, perguntar "qual página?" é ruído.
                 */
                Select::make('page')
                    ->label('Página')
                    ->options(fn (Get $get) => self::opcoesDePagina($get($caminhoDasPaginas) ?? []))
                    ->default(0)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->visible(fn (Get $get) => filled($get($caminhoDasPaginas)))
                    ->columnSpan(1),

                TextInput::make('help')
                    ->label('Texto de ajuda')
                    ->placeholder('Ex: envie em PDF, de preferência')
                    ->helperText('Aparece abaixo do campo, em cinza.')
                    ->maxLength(255)
                    ->columnSpan(2),

                Toggle::make('required')
                    ->label('Resposta obrigatória')
                    ->default(false),

                /*
                 * Rótulo e valor em campos próprios.
                 *
                 * Era uma string só, com o preço dentro de parênteses
                 * (`Futsal(R$5,00)`). O preço virava parte do **nome** da
                 * opção: ficava assim no banco e assim na coluna do gráfico.
                 * Pior, a vírgula do decimal já quebrou a separação em
                 * produção — `M (+30,00)` virava duas opções e os 30 reais
                 * sumiam sem avisar.
                 */
                Repeater::make('options')
                    ->label('Opções de resposta')
                    ->addActionLabel('Adicionar opção')
                    ->schema([
                        TextInput::make('label')
                            ->label('Opção')
                            ->required()
                            ->maxLength(255)
                            ->distinct()
                            ->validationMessages(['distinct' => 'Opção repetida.'])
                            ->columnSpan(2),

                        TextInput::make('surcharge')
                            ->label('Valor adicional')
                            ->helperText('Deixe 0 se não cobra a mais.')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->prefix('R$'),
                    ])
                    ->columns(3)
                    ->defaultItems(2)
                    ->reorderableWithButtons()
                    ->visible(fn (Get $get) => in_array($get('type'), Event::tiposComOpcoes(), true))
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Imagem da pergunta')
                    ->helperText('Opcional. Aparece acima do enunciado, no formulário.')
                    ->image()
                    // Disco público: esta imagem é parte da página de
                    // inscrição, vista por qualquer visitante.
                    ->disk('public')
                    ->directory('event-fields')
                    ->maxSize(2048)
                    ->imageEditor()
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed(fn (?array $state) => filled($state))
            /*
             * Sem isto, cada pergunta fechada mostra só "Item 1". Com várias
             * páginas, o número na frente é o que responde "onde essa pergunta
             * vai parar?" sem precisar abrir uma por uma.
             */
            ->itemLabel(function (array $state, Get $get) use ($caminhoDasPaginas): string {
                $pergunta = $state['question'] ?? 'Nova pergunta';

                if (blank($get($caminhoDasPaginas))) {
                    return $pergunta;
                }

                return (((int) ($state['page'] ?? 0)) + 1).'. '.$pergunta;
            })
            ->reorderableWithButtons();
    }
}
