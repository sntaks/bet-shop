<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Enums\ActivityAction;
use App\Filament\Resources\Shops\ShopResource;
use App\Models\ActivityLog;
use App\Models\Shop;
use App\Services\ShopKeyVerifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * @property Shop $record
 */
class CreateShop extends CreateRecord
{
    protected static string $resource = ShopResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $problem = ShopKeyVerifier::problem((string) $data['api_key'], (string) $data['shop_no']);

        if ($problem !== null) {
            throw ValidationException::withMessages(['data.api_key' => $problem]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        ActivityLog::record(ActivityAction::ShopCreated, [
            'shop_id' => $this->record->id,
            'subject_id' => $this->record->shop_no,
            'details' => [
                'name' => $this->record->name,
                'is_active' => $this->record->is_active,
                'key' => $this->record->key_hint,
            ],
        ]);
    }
}
