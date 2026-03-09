<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertNotificationContactRequest;
use App\Models\NotificationContact;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class NotificationContactController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(UpsertNotificationContactRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);

        DB::transaction(function () use ($request): void {
            $data = $request->contactData();

            if ($data['is_primary']) {
                $this->workspaces->current($request)->notificationContacts()->update(['is_primary' => false]);
            }

            $this->workspaces->current($request)->notificationContacts()->create($data);
        });

        return back()->with('success', 'Notification contact added.');
    }

    public function update(UpsertNotificationContactRequest $request, NotificationContact $notificationContact): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);
        abort_unless($notificationContact->user_id === $workspace->id, 404);

        DB::transaction(function () use ($request, $notificationContact): void {
            $data = $request->contactData();

            if ($data['is_primary']) {
                $this->workspaces->current($request)->notificationContacts()->whereKeyNot($notificationContact->id)->update(['is_primary' => false]);
            }

            $notificationContact->update($data);
        });

        return back()->with('success', 'Notification contact updated.');
    }

    public function destroy(NotificationContact $notificationContact): RedirectResponse
    {
        $request = request();
        $workspace = $this->workspaces->current($request);
        $user = $request->user();

        abort_unless($notificationContact->user_id === $workspace->id, 404);

        DB::transaction(function () use ($notificationContact, $workspace): void {
            $wasPrimary = $notificationContact->is_primary;
            $notificationContact->delete();

            if ($wasPrimary) {
                $replacement = $workspace->notificationContacts()->orderByDesc('enabled')->oldest()->first();

                if ($replacement) {
                    $replacement->update(['is_primary' => true]);
                }
            }
        });

        return back()->with('success', 'Notification contact removed.');
    }
}
