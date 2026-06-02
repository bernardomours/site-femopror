<?php

namespace App\Filament\Resources\Registrations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Participante')->schema([
                    Select::make('event_id')
                        ->relationship('event', 'title')
                        ->label('Evento')
                        ->required()
                        ->searchable()
                        ->preload(),
                        
                    Select::make('church_id')
                        ->relationship('church', 'name')
                        ->label('Igreja/Federação (Opcional)')
                        ->searchable()
                        ->preload(),
                        
                    TextInput::make('name')
                        ->label('Nome Completo')
                        ->required(),
                        
                    TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->required(),
                        
                    TextInput::make('phone')
                        ->label('Telefone/WhatsApp')
                        ->required(),
                ])->columns(2),

                Section::make('Pagamento e Detalhes')->schema([
                    Select::make('payment_status')
                        ->label('Status do Pagamento')
                        ->options([
                            'pending' => 'Aguardando Pagamento',
                            'paid' => 'Pago / Confirmado',
                            'failed' => 'Falhou / Cancelado',
                        ])
                        ->default('pending')
                        ->required(),
                        
                    FileUpload::make('receipt_path')
                        ->label('Comprovante de Pagamento')
                        ->directory('receipts')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->openable()
                        ->downloadable(),
                        
                    KeyValue::make('custom_answers')
                        ->label('Respostas do Formulário Dinâmico')
                        ->keyLabel('Pergunta')
                        ->valueLabel('Resposta')
                        ->columnSpanFull()
                        ->disabled(),
                ])->columns(2),
            ]);
    }
}