<?php

namespace App\Filament\Resources\Agreements;

use App\Enums\AgreementStatus;
use App\Filament\Resources\Agreements\Pages\ListAgreements;
use App\Filament\Resources\Agreements\Pages\ViewAgreement;
use App\Models\Agreement;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgreementResource extends Resource
{
    protected static ?string $model = Agreement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Согласования';

    protected static ?string $modelLabel = 'согласование';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Название'),
            TextEntry::make('status')->label('Статус'),
            TextEntry::make('author.name')->label('Автор'),
            TextEntry::make('deadline')->dateTime()->label('Дедлайн'),
            TextEntry::make('is_approved')->label('Согласовано')->formatStateUsing(fn ($state) => $state === null ? '—' : ($state ? 'Да' : 'Нет')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('title')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state instanceof AgreementStatus ? $state->label() : $state),
            TextColumn::make('author.name')->label('Автор'),
            TextColumn::make('deadline')->dateTime('d.m.Y H:i'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgreements::route('/'),
            'view' => ViewAgreement::route('/{record}'),
        ];
    }
}
