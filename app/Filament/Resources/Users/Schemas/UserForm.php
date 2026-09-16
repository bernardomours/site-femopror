<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->revealable()
                    ->rule('min:8')
                    ->confirmed()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Deixe em branco para manter a senha atual.' : null)
                    ->maxLength(255),

                // Faltava a confirmação: um erro de digitação na senha trancava
                // o usuário para fora sem ninguém perceber.
                TextInput::make('password_confirmation')
                    ->label('Confirme a senha')
                    ->password()
                    ->revealable()
                    ->dehydrated(false)
                    ->required(fn (string $operation, $get): bool => $operation === 'create' || filled($get('password'))),

                TextInput::make('phone')
                    ->label('WhatsApp')
                    ->tel()
                    ->maxLength(20)
                    ->helperText('Preenchido pelo próprio usuário em /profile.'),

                Select::make('church_id')
                    ->label('Igreja')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('A igreja de que a pessoa faz parte. Ela mesma escolhe isso no perfil.'),

                // Ter igreja NÃO dá acesso ao /ump: todo jovem escolhe a própria
                // igreja no perfil. Quem é presidente é decisão da diretoria, e
                // é só isso que abre o painel da UMP.
                Toggle::make('is_church_president')
                    ->label('É presidente da UMP local')
                    ->helperText('Dá acesso ao painel /ump para enviar a inscrição do congresso desta igreja.')
                    ->default(false)
                    ->disabled(fn ($get) => blank($get('church_id')))
                    ->columnSpanFull(),

                Toggle::make('is_admin')
                    ->label('Acesso de Administrador')
                    ->helperText('Enxerga todas as igrejas, comprovantes e delegações.')
                    ->default(false)
                    // Ninguém tira o próprio acesso e fica preso do lado de fora.
                    ->disabled(fn (?\App\Models\User $record) => $record?->getKey() === auth()->id())
                    ->dehydrated(fn (?\App\Models\User $record) => $record?->getKey() !== auth()->id())
                    ->columnSpanFull(),
            ]);
    }
}
