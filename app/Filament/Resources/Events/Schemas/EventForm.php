<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Schemas\ConstrutorDePerguntas;
use App\Models\Event;
use App\Models\FormTemplate;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\FileUpload;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes Principais')->schema([
                    FileUpload::make('image')
                        ->image()
                        ->disk('public')
                        ->directory('events-images')
                        ->label('Imagem de Capa'),
                    TextInput::make('title')
                        ->label('Título do Evento')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        
                    TextInput::make('slug')
                        ->label('URL Amigável (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true),
                        
                    Textarea::make('description')
                        ->label('Descrição')
                        ->columnSpanFull(),
                        
                    DateTimePicker::make('event_date')
                        ->label('Data e Hora')
                        ->required(),
                    
                    DateTimePicker::make('opening_date')
                        ->label('Data de Abertura das Inscrições')
                        ->helperText('Se deixar em branco, as inscrições abrem imediatamente. Se colocar uma data futura, o botão ficará como "Em breve".')
                        ->nullable(),
                                        
                    TextInput::make('location')
                        ->label('Local'),
                        
                    TextInput::make('price')
                        ->label('Valor da Inscrição')
                        ->numeric()
                        ->default(0.00)
                        ->prefix('R$'),
                        
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Rascunho (Oculto)',
                            'published' => 'Publicado (Inscrições Abertas)',
                            'closed' => 'Encerrado',
                        ])
                        ->native(false)
                        ->default('draft')
                        ->required(),

                    // Isto era deduzido de str_contains($title, 'congresso'):
                    // renomear o evento desligava o fluxo de delegado/visitante
                    // e zerava a contagem do painel.
                    Toggle::make('is_congress')
                        ->label('É um congresso?')
                        ->helperText('Liga a escolha entre "Sou Visitante" e "Sou Delegado" na página do evento, e o painel de inscrições das UMPs.')
                        ->default(false)
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Configurações de Inscrição')->schema([
                    Toggle::make('requires_receipt')
                        ->label('Exigir envio de comprovante PIX?')
                        ->helperText('Se marcado, o jovem será obrigado a anexar uma imagem/PDF ao se inscrever.')
                        ->default(false),

                    /*
                     * Atalho para o conjunto que todo congresso usa. Sem ele,
                     * cada edição da diretoria redigitava as mesmas perguntas e
                     * o texto saía um pouco diferente a cada ano — e, como os
                     * gráficos do painel de acompanhamento agrupam pelo **texto
                     * da pergunta**, comparar um congresso com o do ano
                     * anterior deixava de funcionar.
                     *
                     * Fica como componente do schema, e não como ação do
                     * Repeater: `Repeater::headerActions()` não existe no
                     * Filament 5 (`BadMethodCallException` ao abrir a tela).
                     */
                    Actions::make([
                        Action::make('carregarModelo')
                            ->label('Carregar modelo')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->visible(fn () => FormTemplate::query()->exists())
                            ->modalHeading('Carregar um modelo de formulário')
                            ->modalDescription('As perguntas abaixo são substituídas pelas do modelo. Nada é salvo até você clicar em salvar o evento.')
                            ->modalSubmitActionLabel('Carregar')
                            ->schema([
                                Select::make('modelo')
                                    ->label('Modelo')
                                    ->options(fn () => FormTemplate::orderBy('name')->pluck('name', 'id'))
                                    ->required()
                                    ->native(false)
                                    ->helperText('Um modelo é ponto de partida: editar o modelo depois não mexe neste evento.'),
                            ])
                            // `$data` é o que veio do modal (o modelo escolhido);
                            // `$set` escreve no formulário do evento, por fora
                            // dele. Os dois escopos coexistem na mesma closure.
                            ->action(function (array $data, Set $set): void {
                                $modelo = FormTemplate::find($data['modelo']);

                                if ($modelo) {
                                    $set('custom_fields', $modelo->fields ?? []);
                                }
                            }),

                        Action::make('salvarComoModelo')
                            ->label('Salvar como modelo')
                            ->icon('heroicon-m-bookmark')
                            ->color('gray')
                            ->visible(fn (Get $get) => filled($get('custom_fields')))
                            ->modalHeading('Salvar estas perguntas como modelo')
                            ->modalSubmitActionLabel('Salvar modelo')
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome do modelo')
                                    ->placeholder('Ex: Congresso — padrão')
                                    ->required()
                                    ->maxLength(255)
                                    /*
                                     * Regra crua do Laravel, não o `unique()`
                                     * do Filament.
                                     *
                                     * Dentro de um Resource, o `unique()` do
                                     * Filament acrescenta sozinho o "ignore"
                                     * do registro em edição. Como o registro
                                     * aqui é o **evento** e a tabela checada é
                                     * `form_templates`, a consulta saía
                                     * `... from form_templates where name = ?
                                     * and events.id <> 7` e estourava com
                                     * "no such column: events.id". Só acontece
                                     * ao salvar modelo a partir de um evento
                                     * já existente — na tela de criar não há
                                     * registro para ignorar, e passa.
                                     */
                                    ->rule(Rule::unique('form_templates', 'name'))
                                    ->validationMessages([
                                        'unique' => 'Já existe um modelo com este nome.',
                                    ]),

                                TextInput::make('descricao')
                                    ->label('Descrição')
                                    ->placeholder('Opcional — para quem for escolher depois')
                                    ->maxLength(255),
                            ])
                            ->action(function (array $data, Get $get): void {
                                FormTemplate::create([
                                    'name' => $data['nome'],
                                    'description' => $data['descricao'] ?? null,
                                    // Cópia do estado atual do formulário: o
                                    // modelo não acompanha o evento depois.
                                    'fields' => $get('custom_fields') ?? [],
                                ]);

                                Notification::make()
                                    ->title('Modelo salvo')
                                    ->body('Ele já aparece em "Carregar modelo" nos próximos eventos.')
                                    ->success()
                                    ->send();
                            }),
                    // A chave torna estas ações endereçáveis em teste
                    // (`callFormComponentAction('acoesDeModelo', …)`).
                    ])->key('acoesDeModelo')->columnSpanFull(),

                    /*
                     * O construtor vive em `ConstrutorDePerguntas` porque os
                     * modelos de formulário usam exatamente o mesmo repeater.
                     * Duas cópias divergiriam no primeiro campo novo, e aí o
                     * modelo geraria pergunta que o evento não sabe ler.
                     */
                    ConstrutorDePerguntas::paginas('form_pages'),

                    ConstrutorDePerguntas::repeater('custom_fields')
                        ->label('Perguntas personalizadas para este evento')
                        ->helperText('Nome, e-mail, WhatsApp e igreja já são perguntados automaticamente — não repita aqui.'),
                ]),
            ]);
    }
}