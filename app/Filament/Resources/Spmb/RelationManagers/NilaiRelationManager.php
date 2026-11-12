<?php

namespace App\Filament\Resources\Spmb\RelationManagers;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\SpmbTahapSeleksi;
use App\Services\Spmb\AlurSeleksiSpmb;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NilaiRelationManager extends RelationManager
{
    protected static string $relationship = 'nilai';

    protected static ?string $title = 'Nilai seleksi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahap_id')
                ->label('Tahap seleksi')
                ->options(fn () => SpmbTahapSeleksi::query()
                    ->where('periode_id', $this->getOwnerRecord()->periode_id)
                    ->orderBy('urutan')->pluck('nama', 'id'))
                ->required()
                ->disabledOn('edit'),
            DatePicker::make('tanggal')->default(today())->required(),
            TextInput::make('nilai')->numeric()->minValue(0)->maxValue(100),
            Textarea::make('catatan'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('tahap'))
            ->columns([
                TextColumn::make('tahap.nama')->label('Tahap'),
                TextColumn::make('nilai'),
                TextColumn::make('tanggal')->date('d/m/Y'),
                TextColumn::make('catatan')->limit(50),
            ])
            ->headerActions([
                CreateAction::make()->label('Catat nilai')
                    ->using(fn (array $data): Model => $this->simpan($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Model $record, array $data): Model => $this->simpan($data + ['tahap_id' => $record->tahap_id])),
            ]);
    }

    private function simpan(array $data): Model
    {
        try {
            return app(AlurSeleksiSpmb::class)->catatNilai(
                $this->getOwnerRecord(),
                (int) $data['tahap_id'],
                isset($data['nilai']) && $data['nilai'] !== '' ? (float) $data['nilai'] : null,
                $data['tanggal'] ?? null,
                $data['catatan'] ?? null,
            );
        } catch (TransisiTidakValid $e) {
            Notification::make()->title('Nilai tidak dapat dicatat')->body($e->getMessage())->danger()->send();

            throw new Halt();
        }
    }
}
