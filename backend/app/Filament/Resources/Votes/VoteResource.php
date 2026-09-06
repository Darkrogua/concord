<?php

namespace App\Filament\Resources\Votes;

use App\Filament\Resources\Votes\Pages\ListVotes;
use App\Models\Vote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VoteResource extends Resource
{
    protected static ?string $model = Vote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'Голоса';

    protected static ?string $modelLabel = 'голос';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('section.agreement.title')->label('Согласование')->limit(40),
                TextColumn::make('section.name')->label('Раздел'),
                TextColumn::make('user.name')->label('Пользователь'),
                TextColumn::make('vote')->badge(),
                TextColumn::make('comment')->limit(40),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVotes::route('/'),
        ];
    }
}
