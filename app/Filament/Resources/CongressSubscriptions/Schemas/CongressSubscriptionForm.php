<?php

namespace App\Filament\Resources\CongressSubscriptions\Schemas;

use App\Models\CongressSubscription;
use Filament\Forms\Components\Placeholder;
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

                    /*
                     * Quem enviou. A tabela guardava só a igreja, então a
                     * secretaria sabia de qual UMP veio, mas não com quem
                     * falar sobre uma pendência.
                     */
                    Placeholder::make('enviada_por')
                        ->label('Enviada por')
                        ->columnSpanFull()
                        ->content(function (?CongressSubscription $record): string {
                            $presidente = $record?->submittedBy;

                            if (! $presidente) {
                                // Inscrição anterior a esta coluna. Dizer isso é
                                // melhor que chutar o presidente atual da igreja:
                                // a presidência troca, e o chute viraria histórico
                                // errado.
                                return 'Não registrado (inscrição enviada antes deste campo existir).';
                            }

                            return $presidente->name.' · '.$presidente->email;
                        }),

                    FileUpload::make('receipt_path')
                        ->label('Comprovante de Pagamento (PIX)')
                        // Disco privado: o link gerado é assinado e expira, em
                        // vez de ficar valendo para sempre para qualquer um.
                        ->disk(config('femopror.uploads.disk'))
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Relatórios e Documentos')
                ->schema([
                    Repeater::make('documents')
                        ->relationship()
                        ->label('Documentos enviados')
                            ->hiddenLabel()
                        ->schema([
                            TextInput::make('document_type')
                                ->label('Tipo de Documento')
                                ->formatStateUsing(fn ($state) => strtoupper($state)),
                            
                            FileUpload::make('file_path')
                                ->label('Arquivo (PDF)')
                                ->disk(config('femopror.uploads.disk'))
                                ->visibility('private')
                                ->downloadable()
                                ->openable(),
                        ])
                        ->columns(2)
                        ->disabled()
                        ->dehydrated(false)
                        ->deletable(false)
                        ->addable(false),
                ]),

            Section::make('Lista de Inscritos')
                ->schema([
                    Repeater::make('delegates')
                        ->relationship()
                        ->label('Participantes')
                            ->hiddenLabel()
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
