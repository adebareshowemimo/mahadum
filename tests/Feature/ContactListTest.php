<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactListTest extends TestCase
{
    use RefreshDatabase;

    private function list(): ContactList
    {
        return ContactList::create(['name' => 'Diaspora newsletter']);
    }

    public function test_preview_classifies_valid_duplicate_invalid_and_suppressed(): void
    {
        $this->seedRbac();
        $list = $this->list();
        Contact::create(['contact_list_id' => $list->id, 'email' => 'existing@example.test', 'status' => 'subscribed']);
        EmailSuppression::create(['email' => 'blocked@example.test', 'reason' => 'unsubscribe']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/contact-lists/{$list->id}/import/preview", [
            'emails' => "new1@example.test\nNEW2@example.test\nexisting@example.test\nnot-an-email\nblocked@example.test",
        ])
            ->assertOk()
            ->assertJsonPath('data.counts.total', 5)
            ->assertJsonPath('data.counts.valid', 2)
            ->assertJsonPath('data.counts.duplicate', 1)
            ->assertJsonPath('data.counts.invalid', 1)
            ->assertJsonPath('data.counts.suppressed', 1);
    }

    public function test_import_inserts_subscribed_contacts_and_skips_repeats(): void
    {
        $this->seedRbac();
        $list = $this->list();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $payload = ['contacts' => [
            ['email' => 'a@example.test', 'name' => 'Ada'],
            ['email' => 'b@example.test'],
            ['email' => 'a@example.test'], // in-payload duplicate
        ]];

        $this->postJson("/api/v1/admin/contact-lists/{$list->id}/import", $payload)
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseHas('contacts', [
            'contact_list_id' => $list->id, 'email' => 'a@example.test', 'name' => 'Ada',
            'status' => 'subscribed', 'source' => 'upload',
        ]);

        // Re-importing the same address is a no-op.
        $this->postJson("/api/v1/admin/contact-lists/{$list->id}/import", ['contacts' => [['email' => 'a@example.test']]])
            ->assertOk()->assertJsonPath('data.imported', 0);
        $this->assertSame(2, Contact::where('contact_list_id', $list->id)->count());
    }

    public function test_import_skips_suppressed_addresses(): void
    {
        $this->seedRbac();
        $list = $this->list();
        EmailSuppression::create(['email' => 'blocked@example.test', 'reason' => 'bounce']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/contact-lists/{$list->id}/import", ['contacts' => [
            ['email' => 'blocked@example.test'],
            ['email' => 'ok@example.test'],
        ]])->assertOk()->assertJsonPath('data.imported', 1);

        $this->assertDatabaseMissing('contacts', ['email' => 'blocked@example.test']);
    }

    public function test_show_returns_the_list_and_its_contacts(): void
    {
        $this->seedRbac();
        $list = $this->list();
        Contact::create(['contact_list_id' => $list->id, 'email' => 'member@example.test', 'status' => 'subscribed']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson("/api/v1/admin/contact-lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('list.name', 'Diaspora newsletter')      // the list under `list`
            ->assertJsonPath('data.0.email', 'member@example.test')   // contacts under `data`
            ->assertJsonStructure(['list' => ['id', 'name'], 'data', 'meta']);
    }

    public function test_contacts_management_is_super_admin_only(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/admin/contact-lists')->assertStatus(403);
    }
}
