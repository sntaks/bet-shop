<?php

namespace App\Filament\Concerns;

use App\Services\DeskBlock;
use App\Services\KadiResult;
use Filament\Notifications\Notification;

/**
 * What every counter screen does with a KadiApi answer before its own handling: leave for the
 * blocked page when the desk was shut out, and say so when the rate limit was hit.
 */
trait HandlesKadiResults
{
    /**
     * Returns true when the answer was dealt with here and the screen should stop.
     */
    protected function handledCommonOutcome(KadiResult $result): bool
    {
        if (DeskBlock::isBlocked()) {
            $this->redirect(route('filament.shop.blocked'));

            return true;
        }

        if ($result->rateLimited()) {
            Notification::make()
                ->title('Too many requests, wait a moment')
                ->body($result->retryAfter !== null ? "Try again in {$result->retryAfter} seconds." : null)
                ->warning()
                ->send();

            return true;
        }

        return false;
    }
}
