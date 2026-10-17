<?php

namespace App\Filament\Resources\FormTemplates;

use App\Filament\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Filament\Schemas\ConstrutorDePerguntas;
use App\Models\FormTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Conjuntos de perguntas reutilizáveis.
 *
 * O conjunto do congresso vivia numa classe PHP: mudar uma pergunta exigia
 * deploy. Aqui a diretoria cria e edita sozinha.
 *
 * Apagar um modelo **não** mexe nos eventos que já o usaram: carregar um
 * modelo copia o JSON para o evento, e a partir dali os dois são
 * independentes. É o que se quer — evento que já recebeu inscrição não pode
 * mudar de pergunta porque alguém editou um modelo.
 */
class FormTemplateResource extends Resource
{
    protected static ?string $model = FormTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | UnitEnum | null $navigationGroup = 'Inscrições';

    protected static ?string $modelLabel = 'Modelo de Formulário';

    protected static ?string $pluralModelLabel = 'Modelos de Formulário';

    protected static ?string $navigationLabel = 'Modelos de Formulário';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificação')
                ->description('O nome é o que aparece na hora de escolher o modelo, ao cadastrar um evento.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nome do modelo')
                        ->placeholder('Ex: Congresso — padrão')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('description')
                        ->label('Descrição')
                        ->placeholder('Opcional — para quem for escolher depois')
                        ->maxLength(255),
                ]),

            Section::make('Perguntas')
                ->schema([
                    // Mesmo construtor do cadastro de evento, de propósito:
                    // as duas telas guardam a mesma estrutura.
                    ConstrutorDePerguntas::repeater('fields'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('fields')
                    ->label('Perguntas')
                    ->badge()
                    ->formatStateUsing(fn ($state, FormTemplate $record) => $record->quantidadeDePerguntas()),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormTemplates::route('/'),
            'create' => CreateFormTemplate::route('/create'),
            'edit' => EditFormTemplate::route('/{record}/edit'),
        ];
    }
}
