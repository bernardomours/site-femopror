<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class CongressSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Inscrição')
                ->description('Selecione o congresso e anexe o comprovante de pagamento total.')
                ->schema([
                    Select::make('event_id')
                        ->label('Selecione o Evento/Congresso')
                        ->relationship('event', 'title')
                        ->required(),
                    
                    Hidden::make('church_id')
                        ->default(fn () => auth()->user()->church_id),
                    
                    Hidden::make('status')
                        ->default('pendente'),

                    FileUpload::make('receipt_path')
                        ->label('Comprovante de Pagamento (PIX)')
                        ->image()
                        ->directory('congress-receipts')
                        ->required(),
                ])->columns(2),

            Section::make('Envio de Documentos')
                ->description('Anexe os relatórios oficiais da sua UMP em formato PDF.')
                ->schema([
                    Repeater::make('documents')
                        ->relationship()
                        ->label('')
                        ->addActionLabel('Adicionar Documento')
                        ->schema([
                            Select::make('document_type')
                                ->label('Tipo de Documento')
                                ->options([
                                    'delegados' => 'Credencial dos Delegados',
                                    'ata' => 'Relatório de Atas',
                                    'presidente' => 'Relatório do Presidente',
                                    'tesouraria' => 'Relatório de Tesouraria',
                                ])
                                ->required(),
                            
                            FileUpload::make('file_path')
                                ->label('Arquivo (PDF)')
                                ->acceptedFileTypes(['application/pdf'])
                                ->directory('congress-documents')
                                ->required()
                                ->columnSpan(2),
                        ])
                        ->columns(3)
                        ->defaultItems(1),
                ]),

            Section::make('Delegação')
                ->description('Cadastre os jovens que estarão presentes no congresso.')
                ->schema([
                    Repeater::make('delegates')
                        ->relationship()
                        ->label('')
                        ->addActionLabel('Adicionar Participante')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nome Completo')
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->helperText('Para o delegado ter acesso ao portal e acompanhar sua inscrição')
                                ->required(),
                            
                            Select::make('type')
                                ->label('Categoria')
                                ->options([
                                    'delegado' => 'Delegado',
                                ])
                                ->default('delegado')
                                ->required(),
                        ])
                        ->columns(3)
                        ->defaultItems(1),
                ]),

            Section::make('⚠️ Feedback da Secretaria')
                ->description('Leia o motivo da pendência antes de reenviar seus dados.')
                ->schema([
                    Textarea::make('notes')
                        ->label('')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->hidden(fn ($record) => empty($record?->notes)),
        ]);
    }
}
