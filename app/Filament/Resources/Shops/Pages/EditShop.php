<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Enums\ActivityAction;
use App\Filament\Resources\Shops\ShopResource;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Services\DeskBlock;
use App\Services\ShopKeyVerifier;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * @property Shop $record
 */
class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    /**
     * A new key is checked with KadiApi for this shop number before it replaces the old one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['api_key'] ?? null)) {
            $problem = ShopKeyVerifier::problem((string) $data['api_key'], $this->record->shop_no);

            if ($problem !== null) {
                throw ValidationException::withMessages(['data.api_key' => $problem]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())
            ->except(['api_key', 'updated_at'])
            ->all();

        if ($this->record->wasChanged('api_key')) {
            $changes['key'] = $this->record->key_hint;
            DeskBlock::clear($this->record->id);
        }

        if ($changes !== []) {
            ActivityLog::record(ActivityAction::ShopUpdated, [
                'shop_id' => $this->record->id,
                'subject_id' => $this->record->shop_no,
                'details' => $changes,
            ]);
        }
    }
}
