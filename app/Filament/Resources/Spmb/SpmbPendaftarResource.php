<?php

namespace App\Filament\Resources\Spmb;

use App\Filament\Concerns\AksesPanitiaSpmb;
use App\Filament\Resources\Spmb\Pages\EditSpmbPendaftar;
use App\Filament\Resources\Spmb\Pages\ListSpmbPendaftar;
use App\Filament\Resources\Spmb\Pages\ViewSpmbPendaftar;
use App\Filament\Resources\Spmb\RelationManagers\BerkasRelationManager;
use App\Filament\Resources\Spmb\RelationManagers\NilaiRelationManager;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPeriode;
use App\Services\Spmb\AlurSeleksiSpmb;
use App\Support\StatusSpmb;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SpmbPendaftarResource extends Resource
{
    use AksesPanitiaSpmb;

    protected static ?string $model = SpmbPendaftar::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|UnitEnum|null $navigationGroup = 'SPMB';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pendaftar';

    protected static ?string $modelLabel = 'pendaftar';

    protected static ?string $pluralModelLabel = 'pendaftar';

    protected static ?string $slug = 'spmb/pendaftar';

    protected static ?string $recordTitleAttribute = 'nama_lengkap';

    /** Pendaftar masuk lewat formulir publik, bukan dibuat dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Hanya untuk koreksi biodata. Status diubah lewat tindakan di halaman detail. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Biodata calon siswa')->columns(2)->schema([
                TextInput::make('nama_lengkap')->required()->maxLength(255),
                Select::make('jenis_kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->required(),
                TextInput::make('tempat_lahir')->required()->maxLength(100),
                DatePicker::make('tanggal_lahir')->required()->maxDate(today()),
                TextInput::make('nik')->label('NIK')
                    ->rules(['nullable', 'digits:16'])
                    ->rule(fn (?Model $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $nik = preg_replace('/\D+/', '', (string) $value);
                        if ($nik === '' || $record === null) {
                            return;
                        }

                        $sudahAda = SpmbPendaftar::query()->withoutGlobalScopes()
                            ->where('periode_id', $record->periode_id)
                            ->where('nik_hash', SpmbPendaftar::hashNik($nik))
                            ->whereKeyNot($record->getKey())
                            ->exists();

                        if ($sudahAda) {
                            $fail('NIK ini sudah terdaftar pada periode ini.');
                        }
                    }),
                TextInput::make('nisn')->label('NISN')->rules(['nullable', 'digits:10']),
                Select::make('agama_ref')->label('Agama')
                    ->options(fn () => DB::table('referensi')->where('kategori', 'agama')->orderBy('urutan')->pluck('label', 'kode')),
                TextInput::make('asal_sekolah')->maxLength(255),
                Textarea::make('alamat')->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pendaftaran')->columns(3)->schema([
                TextEntry::make('nomor_pendaftaran')->label('Nomor')->copyable(),
                TextEntry::make('status')->badge()
                    ->color(fn (?string $state) => StatusSpmb::warna($state))
                    ->formatStateUsing(fn (?string $state) => StatusSpmb::label($state)),
                TextEntry::make('tanggal_daftar')->label('Tanggal daftar')->date('d/m/Y'),
                TextEntry::make('sekolah.nama')->label('Sekolah'),
                TextEntry::make('periode.nama')->label('Periode'),
                TextEntry::make('jalur.nama')->label('Jalur'),
            ]),
            Section::make('Calon siswa')->columns(3)->schema([
                TextEntry::make('nama_lengkap')->label('Nama'),
                TextEntry::make('jenis_kelamin')->label('Jenis kelamin')
                    ->formatStateUsing(fn (?string $state) => $state === 'L' ? 'Laki-laki' : ($state === 'P' ? 'Perempuan' : '-')),
                TextEntry::make('tempat_lahir')->label('Tempat lahir'),
                TextEntry::make('tanggal_lahir')->label('Tanggal lahir')->date('d/m/Y'),
                TextEntry::make('nik')->label('NIK')
                    ->formatStateUsing(fn (?string $state) => filled($state) ? substr($state, 0, 4).str_repeat('*', 8).substr($state, -4) : '-'),
                TextEntry::make('nisn')->label('NISN')->placeholder('-'),
                TextEntry::make('asal_sekolah')->label('Asal sekolah')->placeholder('-'),
                TextEntry::make('alamat')->placeholder('-')->columnSpan(2),
            ]),
            Section::make('Orang tua / wali')->schema([
                RepeatableEntry::make('wali')->label('')->columns(4)->schema([
                    TextEntry::make('hubungan')->formatStateUsing(fn (?string $state) => ucfirst((string) $state)),
                    TextEntry::make('nama'),
                    TextEntry::make('telepon')->placeholder('-'),
                    TextEntry::make('is_kontak_utama')->label('Kontak utama')
                        ->formatStateUsing(fn ($state) => $state ? 'Ya' : ''),
                ]),
            ]),
            Section::make('Seleksi dan hasil')->columns(3)->schema([
                TextEntry::make('skor_berjalan')->label('Skor berjalan')
                    ->state(fn (SpmbPendaftar $record) => app(AlurSeleksiSpmb::class)->hitungSkor($record))
                    ->placeholder('Belum ada nilai'),
                TextEntry::make('hasil.keputusan')->label('Keputusan')
                    ->formatStateUsing(fn (?string $state) => StatusSpmb::label($state))->placeholder('-'),
                TextEntry::make('hasil.peringkat')->label('Peringkat')->placeholder('-'),
                TextEntry::make('daftarUlang.status')->label('Daftar ulang')->placeholder('-'),
                TextEntry::make('siswa.nis_yayasan')->label('NIS yayasan')->placeholder('-'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['periode', 'jalur', 'sekolah']))
            ->columns([
                TextColumn::make('nomor_pendaftaran')->label('Nomor')->searchable()->copyable(),
                TextColumn::make('nama_lengkap')->label('Nama')->searchable(),
                TextColumn::make('jenis_kelamin')->label('JK'),
                TextColumn::make('tanggal_lahir')->label('Tgl lahir')->date('d/m/Y'),
                TextColumn::make('sekolah.nama')->label('Sekolah'),
                TextColumn::make('periode.nama')->label('Periode'),
                TextColumn::make('jalur.nama')->label('Jalur'),
                TextColumn::make('status')->badge()
                    ->color(fn (?string $state) => StatusSpmb::warna($state))
                    ->formatStateUsing(fn (?string $state) => StatusSpmb::label($state)),
                TextColumn::make('tanggal_daftar')->label('Daftar')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusSpmb::LABEL),
                SelectFilter::make('periode_id')->label('Periode')
                    ->options(fn () => SpmbPeriode::query()->with('sekolah')->orderByDesc('id')->get()
                        ->mapWithKeys(fn (SpmbPeriode $p) => [$p->id => ($p->sekolah?->nama ?? '-').' - '.$p->nama])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BerkasRelationManager::class,
            NilaiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpmbPendaftar::route('/'),
            'view' => ViewSpmbPendaftar::route('/{record}'),
            'edit' => EditSpmbPendaftar::route('/{record}/edit'),
        ];
    }
}
