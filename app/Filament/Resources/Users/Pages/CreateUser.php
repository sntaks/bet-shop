<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\ActivityAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property User $record
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $role = $this->data['role'] ?? null;

        $this->record->assignRole($role);

        ActivityLog::record(ActivityAction::StaffCreated, [
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
