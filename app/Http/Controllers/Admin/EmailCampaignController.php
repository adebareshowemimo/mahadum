<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Services\AuditLogger;
use App\Services\Email\CampaignSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Admin email campaigns — compose, target a user segment or a contact list, then
 * send now or schedule. `emails.campaigns.manage` (super-admin-only). Every send
 * runs through the branded template + email log + suppression via CampaignSender.
 */
class EmailCampaignController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $campaigns = EmailCampaign::latest()->get()->map($this->row(...));

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        $campaign = EmailCampaign::create($data);
        $this->audit->record('email_campaign.created', $campaign, [], ['subject' => $campaign->subject]);

        return response()->json(['data' => $this->row($campaign->fresh())], 201);
    }

    public function show(EmailCampaign $emailCampaign): JsonResponse
    {
        $byStatus = $emailCampaign->recipients()
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return response()->json(['data' => [
            ...$this->row($emailCampaign),
            'body' => $emailCampaign->body,
            'audience' => $emailCampaign->audience,
            'recipients_by_status' => $byStatus,
        ]]);
    }

    /** Send a preview to the admin's own address (no recipient rows written). */
    public function test(Request $request, EmailCampaign $emailCampaign): JsonResponse
    {
        Mail::to($request->user()->email)->send(
            new CampaignMail($emailCampaign->subject, $emailCampaign->body, url('/'), $emailCampaign->id)
        );

        return response()->json(['data' => ['sent_to' => $request->user()->email]]);
    }

    /**
     * Send now, or schedule for later if `scheduled_at` is in the future.
     */
    public function send(Request $request, EmailCampaign $emailCampaign, CampaignSender $sender): JsonResponse
    {
        if (in_array($emailCampaign->status, ['sending', 'sent'], true)) {
            return response()->json(['error' => ['code' => 'already_sent', 'message' => 'This campaign has already been sent.']], 409);
        }

        $validated = $request->validate(['scheduled_at' => ['nullable', 'date', 'after:now']]);

        if (! empty($validated['scheduled_at'])) {
            $emailCampaign->update(['status' => 'scheduled', 'scheduled_at' => $validated['scheduled_at']]);
            $this->audit->record('email_campaign.scheduled', $emailCampaign, [], ['scheduled_at' => $validated['scheduled_at']]);

            return response()->json(['data' => $this->row($emailCampaign->fresh())]);
        }

        $sender->send($emailCampaign);
        $this->audit->record('email_campaign.sent', $emailCampaign, [], ['sent_count' => $emailCampaign->fresh()?->sent_count]);

        return response()->json(['data' => $this->row($emailCampaign->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audience_type' => ['required', Rule::in(['user_segment', 'contact_list'])],
            'audience' => ['nullable', 'array'],
            'audience.contact_list_id' => ['nullable', 'integer', 'exists:contact_lists,id'],
            'audience.role' => ['nullable', 'string'],
            'audience.status' => ['nullable', 'string'],
            'audience.organization_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EmailCampaign $c): array
    {
        return [
            'id' => $c->id,
            'subject' => $c->subject,
            'audience_type' => $c->audience_type,
            'status' => $c->status,
            'scheduled_at' => $c->scheduled_at?->toIso8601String(),
            'recipients_count' => $c->recipients_count,
            'sent_count' => $c->sent_count,
            'failed_count' => $c->failed_count,
            'sent_at' => $c->sent_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
