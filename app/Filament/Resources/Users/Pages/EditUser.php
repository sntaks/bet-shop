<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\ActivityAction;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Pages\EditRecord;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $roleBeforeSave = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'role' => $this->record->getRoleNames()->first(),
        ];
    }

    /**
     * Only an admin moves someone to another shop, never themselves; an admin belongs to no shop.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! UserForm::actorIsAdmin() || UserForm::isCurrentUser($this->record)) {
            unset($data['shop_id']);
        } elseif (($this->data['role'] ?? null) === RoleSeeder::ADMIN) {
            $data['shop_id'] = null;
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->roleBeforeSave = $this->record->getRoleNames()->first();
    }

    protected function afterSave(): void
    {
        $changes = collect($this->record->getChanges())
            ->except(['password', 'remember_token', 'updated_at'])
            ->all();

        if ($this->record->wasChanged('password')) {
            $changes['password_changed'] = true;
        }

        $role = $this->data['role'] ?? null;

        if (! UserForm::isCurrentUser($this->record) && is_string($role) && $role !== $this->roleBeforeSave) {
            $this->record->syncRoles([$role]);
            $this->record->unsetRelation('roles');
            $changes['role'] = $role;
        }

        if ($changes !== []) {
            ActivityLog::record(ActivityAction::StaffUpdated, [
                'shop_id' => $this->record->shop_id,
                'subject_id' => (string) $this->record->getKey(),
                'details' => ['email' => $this->record->email, ...$changes],
            ]);
        }
    }
}
