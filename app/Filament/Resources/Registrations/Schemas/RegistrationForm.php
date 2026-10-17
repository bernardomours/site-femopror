<?php

namespace App\Filament\Resources\Registrations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use App\Models\Registration;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                        
                    TextInput::make('amount_paid')
                        ->label('Valor cobrado')
                        ->helperText('Calculado no momento da inscrição, com os adicionais escolhidos.')
                        ->numeric()
                        ->prefix('R$')
                        ->disabled()
                        ->dehydrated(false),

                    FileUpload::make('receipt_path')
                        ->label('Comprovante de Pagamento')
                        // Comprovante bancário sai do disco público.
                        ->disk(config('femopror.uploads.disk'))
                        ->visibility('private')
                        ->directory('receipts')
                        // `->image()` recusava PDF, e vários bancos compartilham
                        // o comprovante justamente em PDF.
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                        ->openable()
                        ->downloadable(),
                        
                    /*
                     * Era um `KeyValue`, que só sabe imprimir texto: resposta
                     * de pergunta do tipo arquivo aparecia como o caminho cru
                     * (`respostas/xY3k….pdf`) — justamente para quem precisa
                     * **abrir** o documento para conferir.
                     */
                    Placeholder::make('respostas')
                        ->label('Respostas do formulário')
                        ->columnSpanFull()
                        ->content(function (?Registration $record): HtmlString {
                            $respostas = $record?->respostasParaExibicao() ?? [];

                            if ($respostas === []) {
                                return new HtmlString('<span class="fi-color-gray">Este evento não tem perguntas personalizadas.</span>');
                            }

                            $linhas = collect($respostas)->map(function (array $r): string {
                                // `e()` em tudo que veio de formulário: pergunta
                                // e resposta são texto digitado pelo público.
                                $pergunta = e($r['pergunta']);

                                $valor = match (true) {
                                    filled($r['rotulo']) && filled($r['url']) => sprintf(
                                        '<a href="%s" target="_blank" rel="noopener" class="fi-link fi-color-primary" style="text-decoration: underline">%s</a>',
                                        e($r['url']),
                                        e($r['rotulo']),
                                    ),
                                    filled($r['rotulo']) => '<span style="opacity:.6">Arquivo indisponível no momento</span>',
                                    blank($r['texto']) => '<span style="opacity:.6">Não respondido</span>',
                                    default => e($r['texto']),
                                };

                                return sprintf(
                                    '<div style="padding:.35rem 0"><div style="font-size:.75rem;opacity:.6">%s</div><div>%s</div></div>',
                                    $pergunta,
                                    $valor,
                                );
                            })->implode('');

                            return new HtmlString($linhas);
                        }),
                ])->columns(2),
            ]);
    }
}