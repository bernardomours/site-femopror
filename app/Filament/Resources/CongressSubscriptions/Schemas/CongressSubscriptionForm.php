<?php

namespace App\Filament\Resources\CongressSubscriptions\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;

class CongressSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Avaliação da Secretaria')
                ->description('Analise a documentação e defina o status desta inscrição.')
                ->schema([
                    Select::make('status')
                        ->label('Status da Inscrição')
                        ->options([
                            'pendente' => 'Pendente em Análise',
                            'aprovado' => 'Aprovado / Confirmado',
                            'recusado' => 'Recusado / Com Pendências',
                        ])
                        ->required()
                        ->native(false),
                    
                    Textarea::make('notes')
                        ->label('Observações (Opcional)')
                        ->helperText('Caso falte algum documento, anote aqui para que a UMP saiba o motivo da recusa.')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Dados Enviados pela UMP')
                ->schema([
                    Select::make('church_id')
                        ->relationship('church', 'name')
                        ->label('Igreja / UMP')
                        ->disabled(),
                    
                    Select::make('event_id')
                        ->relationship('event', 'title')
                        ->label('Congresso')
                        ->disabled(),

                    FileUpload::make('receipt_path')
                        ->label('Comprovante de Pagamento (PIX)')
                        ->downloadable()
                        ->openable()
                        ->disabled()
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Relatórios e Documentos')
                ->schema([
                    Repeater::make('documents')
                        ->relationship()
                        ->label('')
                        ->schema([
                            TextInput::make('document_type')
                                ->label('Tipo de Documento')
                                ->formatStateUsing(fn ($state) => strtoupper($state)),
                            
                            FileUpload::make('file_path')
                                ->label('Arquivo (PDF)')
                                ->downloadable()
                                ->openable(),
                        ])
                        ->columns(2)
                        ->disabled()
                        ->deletable(false) 
                        ->addable(false), 
                ]),

            Section::make('Lista de Inscritos')
                ->schema([
                    Repeater::make('delegates')
                        ->relationship()
                        ->label('')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nome'),
                            TextInput::make('type')
                                ->label('Categoria')
                                ->formatStateUsing(fn ($state) => ucfirst($state)),
                        ])
                        ->columns(2)
                        ->disabled()
                        ->deletable(false)
                        ->addable(false),
                ]),
        ]);
    }
}
