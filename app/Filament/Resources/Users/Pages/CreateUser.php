<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\ActivityAction;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property User $record
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * A manager's new staff always join the manager's own shop; an admin belongs to no shop.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shop_id'] = match (true) {
            ! UserForm::actorIsAdmin() => auth()->user()?->shop_id,
            ($this->data['role'] ?? null) === RoleSeeder::ADMIN => null,
            default => $data['shop_id'] ?? null,
        };

        return $data;
    }

    protected function afterCreate(): void
    {
        $role = $this->data['role'] ?? null;

        $this->record->assignRole($role);

        ActivityLog::record(ActivityAction::StaffCreated, [
            'shop_id' => $this->record->shop_id,
            'subject_id' => (string) $this->record->getKey(),
            'details' => [
                'name' => $this->record->name,
                'email' => $this->record->email,
                'role' => $role,
                'is_active' => $this->record->is_active,
            ],
        ]);
    }
}
