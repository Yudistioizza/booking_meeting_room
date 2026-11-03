<?php

namespace App\Services\Integrations;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outlook / Exchange Online calendar sync through Microsoft Graph, using the
 * client-credentials flow (no user sign-in needed on the server).
 *
 * Events are created in the organizer mailbox (GRAPH_ORGANIZER). The booker
 * and any guests are invited as attendees and the room's Exchange mailbox,
 * when set, is added as a resource so it shows busy in Outlook's Room Finder.
 */
class MicrosoftGraph
{
    private const BASE = 'https://graph.microsoft.com/v1.0';

    public function configured(): bool
    {
        $c = config('services.graph');

        return filled($c['tenant_id'] ?? null) && filled($c['client_id'] ?? null)
            && filled($c['client_secret'] ?? null) && filled($c['organizer'] ?? null);
    }

    /** Create or update the Outlook event for a booking; returns the event id. */
    public function upsert(Booking $b): string
    {
        $b->loadMissing('room', 'creator');
        $payload = $this->payload($b);

        if ($b->outlook_event_id) {
            $this->request('patch', $this->eventUrl($b->outlook_event_id), $payload);

            return $b->outlook_event_id;
        }

        $res = $this->request('post', $this->eventUrl(), $payload + ['transactionId' => $b->ics_uid]);

        return $res['id'];
    }

    public function delete(Booking $b): void
    {
        if (! $b->outlook_event_id) {
            return;
        }
        $this->request('delete', $this->eventUrl($b->outlook_event_id));
    }

    /** Used by the admin page to prove credentials and mailbox are valid. */
    public function test(): string
    {
        $user = $this->request('get', self::BASE . '/users/' . rawurlencode(config('services.graph.organizer')) . '?$select=displayName,mail');

        return $user['displayName'] ?? $user['mail'] ?? config('services.graph.organizer');
    }

    private function payload(Booking $b): array
    {
        $attendees = [['emailAddress' => ['address' => $b->creator->email, 'name' => $b->creator->name], 'type' => 'required']];
        foreach ((array) $b->attendees as $email) {
            $attendees[] = ['emailAddress' => ['address' => $email], 'type' => 'required'];
        }
        if ($b->room->calendar_email) {
            $attendees[] = ['emailAddress' => ['address' => $b->room->calendar_email, 'name' => $b->room->name], 'type' => 'resource'];
        }

        return [
            'subject' => $b->title,
            'body' => ['contentType' => 'text', 'content' => (string) $b->description],
            'start' => ['dateTime' => $b->start_time->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $b->end_time->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'location' => ['displayName' => $b->room->name . ($b->room->location ? ', ' . $b->room->location : '')],
            'attendees' => $attendees,
            'allowNewTimeProposals' => false,
        ];
    }

    private function eventUrl(?string $id = null): string
    {
        return self::BASE . '/users/' . rawurlencode(config('services.graph.organizer')) . '/events' . ($id ? '/' . rawurlencode($id) : '');
    }

    private function request(string $method, string $url, array $json = []): array
    {
        $res = Http::withToken($this->token())->acceptJson()->timeout(15)->retry(2, 300, throw: false)->{$method}($url, $json ?: null);

        if ($res->status() === 401) {
            Cache::forget('graph.token');
        }
        if ($res->failed() && ! ($method === 'delete' && $res->status() === 404)) {
            throw new RuntimeException('Microsoft Graph ' . $res->status() . ': ' . ($res->json('error.message') ?? $res->body()));
        }

        return $res->json() ?? [];
    }

    private function token(): string
    {
        return Cache::remember('graph.token', 3000, function () {
            $c = config('services.graph');
            $res = Http::asForm()->timeout(15)->post("https://login.microsoftonline.com/{$c['tenant_id']}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $c['client_id'],
                'client_secret' => $c['client_secret'],
                'scope' => 'https://graph.microsoft.com/.default',
            ]);
            if ($res->failed()) {
                throw new RuntimeException('Token Microsoft gagal: ' . ($res->json('error_description') ?? $res->status()));
            }

            return $res->json('access_token');
        });
    }
}
