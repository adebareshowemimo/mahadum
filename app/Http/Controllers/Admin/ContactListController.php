<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailSuppression;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contact lists + the email-upload pipeline. An admin builds a mailing list by
 * pasting or uploading a block of addresses; a two-step preview → import flow
 * validates, de-dupes, and honours the global suppression list before anything
 * is stored. `emails.contacts.manage` (super-admin-only).
 */
class ContactListController extends Controller
{
    /** Cap a single upload so a huge paste can't blow memory. */
    private const MAX_UPLOAD_ROWS = 20000;

    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $lists = ContactList::withCount([
            'contacts',
            'contacts as subscribed_count' => fn ($q) => $q->where('status', 'subscribed'),
        ])->latest()->get()->map(fn (ContactList $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'description' => $l->description,
            'contacts' => $l->contacts_count,
            'subscribed' => $l->getAttribute('subscribed_count'),
            'created_at' => $l->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $lists]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['created_by'] = $request->user()->id;

        $list = ContactList::create($data);
        $this->audit->record('contact_list.created', $list, [], ['name' => $list->name]);

        return response()->json(['data' => ['id' => $list->id, 'name' => $list->name]], 201);
    }

    public function show(Request $request, ContactList $contactList): JsonResponse
    {
        $page = $contactList->contacts()->latest()->paginate(50);

        return response()->json([
            'list' => [
                'id' => $contactList->id,
                'name' => $contactList->name,
                'description' => $contactList->description,
            ],
            'data' => collect($page->items())->map(fn (Contact $c) => [
                'id' => $c->id,
                'email' => $c->email,
                'name' => $c->name,
                'status' => $c->status,
                'source' => $c->source,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Stage an upload: parse pasted text and/or a CSV file, then classify every
     * address (valid / duplicate / invalid / suppressed) WITHOUT storing anything.
     * The client shows the counts, then POSTs the `valid` set to `import`.
     */
    public function previewImport(Request $request, ContactList $contactList): JsonResponse
    {
        $request->validate([
            'emails' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $raw = $this->collectRows($request);
        $existing = $contactList->contacts()->pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();

        $valid = [];
        $seen = [];
        $counts = ['total' => 0, 'valid' => 0, 'duplicate' => 0, 'invalid' => 0, 'suppressed' => 0];

        foreach ($raw as [$email, $name]) {
            $counts['total']++;
            $email = mb_strtolower(trim($email));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $counts['invalid']++;

                continue;
            }
            if (isset($seen[$email]) || $existing->has($email)) {
                $counts['duplicate']++;

                continue;
            }
            if (EmailSuppression::suppresses($email)) {
                $counts['suppressed']++;

                continue;
            }

            $seen[$email] = true;
            $valid[] = ['email' => $email, 'name' => $name !== '' ? $name : null];
            $counts['valid']++;
        }

        return response()->json(['data' => ['counts' => $counts, 'valid' => $valid]]);
    }

    /**
     * Commit the reviewed set. Re-checks each address against live state (a
     * contact/suppression may have landed since the preview) and inserts only
     * genuine new subscribers, stamping consent + source.
     */
    public function import(Request $request, ContactList $contactList): JsonResponse
    {
        $data = $request->validate([
            'contacts' => ['required', 'array', 'min:1', 'max:'.self::MAX_UPLOAD_ROWS],
            'contacts.*.email' => ['required', 'email'],
            'contacts.*.name' => ['nullable', 'string', 'max:160'],
        ]);

        $existing = $contactList->contacts()->pluck('email')->map(fn ($e) => mb_strtolower($e))->flip();
        $now = now();
        $imported = 0;
        $seen = [];

        foreach ($data['contacts'] as $row) {
            $email = mb_strtolower(trim($row['email']));
            if (isset($seen[$email]) || $existing->has($email) || EmailSuppression::suppresses($email)) {
                continue;
            }
            $seen[$email] = true;

            Contact::create([
                'contact_list_id' => $contactList->id,
                'email' => $email,
                'name' => $row['name'] ?? null,
                'status' => 'subscribed',
                'source' => 'upload',
                'consent_at' => $now,
            ]);
            $imported++;
        }

        $skipped = count($data['contacts']) - $imported;
        $this->audit->record('contacts.uploaded', $contactList, [], ['imported' => $imported, 'skipped' => $skipped]);

        return response()->json(['data' => ['imported' => $imported, 'skipped' => $skipped]]);
    }

    /** Add a single contact manually (validated + deduped + suppression-checked). */
    public function storeContact(Request $request, ContactList $contactList): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:160'],
        ]);
        $email = mb_strtolower(trim($data['email']));

        if ($contactList->contacts()->where('email', $email)->exists()) {
            return response()->json(['error' => ['code' => 'duplicate', 'message' => 'That address is already on this list.']], 422);
        }
        if (EmailSuppression::suppresses($email)) {
            return response()->json(['error' => ['code' => 'suppressed', 'message' => 'That address is suppressed (unsubscribed or bounced).']], 422);
        }

        $contact = Contact::create([
            'contact_list_id' => $contactList->id,
            'email' => $email,
            'name' => $data['name'] ?? null,
            'status' => 'subscribed',
            'source' => 'manual',
            'consent_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $contact->id, 'email' => $contact->email]], 201);
    }

    /** Edit a contact's name or subscription status. */
    public function updateContact(Request $request, ContactList $contactList, Contact $contact): JsonResponse
    {
        abort_unless($contact->contact_list_id === $contactList->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'in:subscribed,unsubscribed'],
        ]);

        if (array_key_exists('status', $data)) {
            $data['unsubscribed_at'] = $data['status'] === 'unsubscribed' ? now() : null;
        }

        $contact->update($data);

        return response()->json(['data' => ['id' => $contact->id, 'status' => $contact->status]]);
    }

    public function destroyContact(ContactList $contactList, Contact $contact): JsonResponse
    {
        abort_unless($contact->contact_list_id === $contactList->id, 404);
        $contact->delete();

        return response()->json(null, 204);
    }

    /**
     * Gather [email, name] rows from a pasted block and/or an uploaded CSV.
     * Pasted text: one address per line (or comma/semicolon separated), names
     * ignored. CSV: first column email, optional second column name.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function collectRows(Request $request): array
    {
        $rows = [];

        if ($text = trim((string) $request->input('emails', ''))) {
            foreach (preg_split('/[\r\n,;]+/', $text) ?: [] as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $rows[] = [$token, ''];
                }
            }
        }

        if ($request->hasFile('file')) {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            if ($handle !== false) {
                while (($line = fgetcsv($handle)) !== false && count($rows) < self::MAX_UPLOAD_ROWS) {
                    $email = trim((string) ($line[0] ?? ''));
                    if ($email === '' || mb_strtolower($email) === 'email') {
                        continue; // skip blanks + a header row
                    }
                    $rows[] = [$email, trim((string) ($line[1] ?? ''))];
                }
                fclose($handle);
            }
        }

        return array_slice($rows, 0, self::MAX_UPLOAD_ROWS);
    }
}
