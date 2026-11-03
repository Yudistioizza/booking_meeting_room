<?php

namespace App\Services;

use App\Models\Booking;
use App\Services\Integrations\MicrosoftGraph;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the Outlook event in step with a booking. A calendar outage must
 * never block booking a room, so failures are logged, not thrown.
 */
class CalendarSync
{
    public function __construct(private MicrosoftGraph $graph)
    {
    }

    public function enabled(): bool
    {
        return $this->graph->configured();
    }

    public function push(Booking $b): void
    {
        if (! $this->enabled() || $b->status !== 'approved') {
            return;
        }
        try {
            $id = $this->graph->upsert($b);
            $b->forceFill(['outlook_event_id' => $id, 'external_provider' => 'graph', 'outlook_invitation_sent' => true])->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('Outlook sync gagal untuk booking ' . $b->id . ': ' . $e->getMessage());
        }
    }

    public function remove(Booking $b): void
    {
        if (! $this->enabled() || ! $b->outlook_event_id) {
            return;
        }
        try {
            $this->graph->delete($b);
            $b->forceFill(['outlook_event_id' => null, 'outlook_invitation_sent' => false])->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('Outlook hapus event gagal untuk booking ' . $b->id . ': ' . $e->getMessage());
        }
    }
}
