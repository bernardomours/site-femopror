<?php

namespace App\Filament\Ump\Pages;

use App\Models\Board;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * A diretoria da UMP local, preenchida pelo próprio presidente.
 *
 * Existia só no painel da federação: a UMP mandava os nomes por fora e alguém
 * da diretoria digitava. Os nomes aparecem na página pública da igreja, então
 * quem sabe quem são é a própria UMP.
 *
 * É uma **página**, não um Resource com listagem. Uma UMP tem uma diretoria
 * atual; uma listagem convidaria a criar várias e exporia o `is_active`, que é
 * justamente o que a home usa para escolher qual mostrar
 * (`$church->boards->active()`). Aqui o presidente edita a dele e pronto.
 */
class MinhaDiretoria extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $title = 'Minha Diretoria';

    protected static ?string $navigationLabel = 'Minha Diretoria';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    /**
     * Admin da federação entra no `/ump`, mas não tem igreja (`church_id` é
     * nulo): não há diretoria "dele" para editar. Para ele o item nem aparece —
     * o caminho é o painel da federação, que gerencia a diretoria de qualquer
     * igreja.
     */
    public static function canAccess(): bool
    {
        return filled(auth()->user()?->church_id);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($this->diretoria()->attributesToArray());
    }

    /**
     * A diretoria ativa da igreja do usuário, ou uma nova em branco.
     *
     * `church_id` sai de `auth()`, nunca do formulário: é o que impede uma
     * request forjada de gravar a diretoria em cima de outra igreja. Mesmo
     * motivo pelo qual `church_id` e `status` saíram do formulário de inscrição
     * de congresso — ver o padrão de segurança no CLAUDE.md.
     */
    protected function diretoria(): Board
    {
        $igreja = auth()->user()->church_id;

        return Board::query()->where('church_id', $igreja)->active()->first()
            ?? Board::query()->where('church_id', $igreja)->latest('id')->first()
            ?? new Board(['church_id' => $igreja, 'is_active' => true]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Diretoria em exercício')
                ->description('Estes nomes aparecem na página pública da sua UMP. Só o presidente é obrigatório — deixe em branco os cargos que a sua diretoria não tem.')
                ->columns(2)
                ->schema([
                    TextInput::make('president_name')
                        ->label('Presidente')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('vice_president_name')
                        ->label('Vice-Presidente')
                        ->maxLength(255),

                    TextInput::make('first_secretary_name')
                        ->label('1º Secretário(a)')
                        ->maxLength(255),

                    TextInput::make('second_secretary_name')
                        ->label('2º Secretário(a)')
                        ->maxLength(255),

                    TextInput::make('executive_secretary_name')
                        ->label('Secretário(a) Executivo(a)')
                        ->maxLength(255),

                    TextInput::make('treasurer_name')
                        ->label('Tesoureiro(a)')
                        ->maxLength(255),
                ]),

            Section::make('Foto oficial')
                ->description('Opcional. Aparece junto dos nomes na página pública.')
                ->schema([
                    FileUpload::make('image_path')
                        // `label('')` não esconde: o Filament cai no nome do
                        // atributo e a tela mostrava "Image path".
                        ->hiddenLabel()
                        ->image()
                        // Disco público de propósito, ao contrário de comprovante
                        // e relatório: esta foto é feita para ser vista por
                        // qualquer visitante do site.
                        ->disk('public')
                        ->directory('boards')
                        ->maxSize(4096)
                        ->imageEditor()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Salvar diretoria')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $dados = $this->form->getState();

        $diretoria = $this->diretoria();

        /*
         * Só os campos do formulário. `church_id` e `is_active` são definidos
         * aqui, no servidor, e não saem de `$dados`: propriedade pública de
         * componente Livewire é adulterável por request forjada, mesmo sem
         * campo correspondente na tela.
         */
        $diretoria->fill(Arr::only($dados, [
            'president_name',
            'vice_president_name',
            'first_secretary_name',
            'second_secretary_name',
            'executive_secretary_name',
            'treasurer_name',
            'image_path',
        ]));

        $diretoria->church_id = auth()->user()->church_id;
        $diretoria->is_active = true;

        $diretoria->save();

        Notification::make()
            ->title('Diretoria salva')
            ->body('Os nomes já aparecem na página da sua UMP no site.')
            ->success()
            ->send();
    }
}
