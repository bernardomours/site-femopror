<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes Principais')->schema([
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
                        ->default('draft')
                        ->required(),
                ])->columns(2),

                Section::make('Configurações de Inscrição')->schema([
                    Toggle::make('requires_receipt')
                        ->label('Exigir envio de comprovante PIX?')
                        ->helperText('Se marcado, o jovem será obrigado a anexar uma imagem/PDF ao se inscrever.')
                        ->default(false),

                    Repeater::make('custom_fields')
                        ->label('Perguntas Personalizadas para este Evento')
                        ->helperText('Adicione perguntas extras que aparecerão no formulário do site.')
                        ->schema([
                            TextInput::make('question')
                                ->label('Pergunta (Ex: Qual o tamanho da sua camisa?)')
                                ->required(),
                                
                            Select::make('type')
                                ->label('Tipo de Resposta')
                                ->options([
                                    'text' => 'Texto Livre',
                                    'select' => 'Múltipla Escolha',
                                ])
                                ->required()
                                ->live(),
                                
                            TextInput::make('options')
                                ->label('Opções de Resposta')
                                ->helperText('Separe as opções por vírgula. Ex: P, M, G, GG')
                                ->visible(fn (Get $get) => $get('type') === 'select'),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->reorderableWithButtons(),
                ]),
            ]);
    }
}