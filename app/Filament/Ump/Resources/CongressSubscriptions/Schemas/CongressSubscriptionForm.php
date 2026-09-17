<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Schemas;

use App\Models\Event;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CongressSubscriptionForm
{
    /**
     * `church_id` e `status` NÃO estão neste schema, e é de propósito.
     *
     * Eram dois `Hidden` com `default()`. Campo Hidden do Filament vive no
     * estado Livewire, que é adulterável por request forjada: dava para mandar
     * `status: aprovado` e se autoaprovar, ou gravar a inscrição no `church_id`
     * de outra igreja. Os dois passaram a ser definidos no servidor, em
     * CreateCongressSubscription::mutateFormDataBeforeCreate().
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Inscrição')
                    ->description('Selecione o congresso e anexe o comprovante de pagamento total.')
                    ->schema([
                        Select::make('event_id')
                            ->label('Selecione o Evento/Congresso')
                            // Só congresso publicado e com inscrição aberta. A
                            // relationship() crua listava qualquer evento, até
                            // rascunho e encerrado.
                            ->options(fn () => Event::query()
                                ->where('is_congress', true)
                                ->published()
                                ->orderByDesc('event_date')
                                ->pluck('title', 'id'))
                            ->native(false)
                            ->required()
                            ->exists('events', 'id'),

                        FileUpload::make('receipt_path')
                            ->label('Comprovante de Pagamento (PIX)')
                            // Aceita PDF: é como boa parte dos bancos compartilha.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            // Disco privado: comprovante bancário não pode ficar
                            // acessível por URL direta sem autenticação.
                            ->disk(config('femopror.uploads.disk'))
                            ->visibility('private')
                            ->directory('congress-receipts')
                            ->maxSize(4096)
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
                                    ->native(false)
                                    ->required(),

                                FileUpload::make('file_path')
                                    ->label('Arquivo (PDF)')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk(config('femopror.uploads.disk'))
                                    ->visibility('private')
                                    ->directory('congress-documents')
                                    ->maxSize(8192)
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
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->helperText('Para o delegado ter acesso ao portal e acompanhar sua inscrição')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('type')
                                    ->label('Categoria')
                                    ->options([
                                        'delegado' => 'Delegado',
                                        'visitante' => 'Visitante',
                                    ])
                                    ->default('delegado')
                                    ->native(false)
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
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => blank($record?->notes)),
            ]);
    }
}
