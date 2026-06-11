<?php

namespace App\Filament\Resources\Downloads\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;

class DownloadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes do Arquivo')
                ->schema([
                    TextInput::make('title')
                        ->label('Título do Documento')
                        ->required()
                        ->maxLength(255),
                    
                    TextInput::make('description')
                        ->label('Descrição Curta')
                        ->maxLength(255),
                    
                    FileUpload::make('file_path')
                        ->label('Arquivo')
                        ->disk('public')
                        ->directory('downloads-publicos')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'audio/mpeg'])
                        ->required()
                        ->columnSpanFull(),
                    
                    Select::make('icon')
                        ->label('Ícone Visual')
                        ->options([
                            'heroicon-o-document-text' => 'Documento PDF',
                            'heroicon-o-book-open' => 'Livro / Manual',
                            'heroicon-o-musical-note' => 'Áudio / Música',
                            'heroicon-o-photo' => 'Imagem / Arte',
                        ])
                        ->default('heroicon-o-document-text')
                        ->native(false),

                    Toggle::make('is_active')
                        ->label('Visível no site')
                        ->default(true),
                ])->columns(2),
        ]);
    }
}
