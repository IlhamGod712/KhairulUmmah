<?php

use App\Models\Notifikasi;
use App\Models\User;

test('wali only sees own notifications', function (): void {
    $wali1 = User::factory()->wali()->create();
    $wali2 = User::factory()->wali()->create();
    Notifikasi::create(['user_id' => $wali1->id, 'tipe' => 'test', 'judul' => 'A', 'pesan' => 'x']);
    Notifikasi::create(['user_id' => $wali2->id, 'tipe' => 'test', 'judul' => 'B', 'pesan' => 'y']);

    $res = $this->actingAs($wali1)->getJson('/api/wali/notifikasi')->assertOk()->json();
    expect(count($res['data']['items']))->toBe(1);
});

test('guest cannot access notifications', function (): void {
    $this->getJson('/api/wali/notifikasi')->assertStatus(401);
});

test('wali cannot read other user notification', function (): void {
    $wali1 = User::factory()->wali()->create();
    $wali2 = User::factory()->wali()->create();
    $n = Notifikasi::create(['user_id' => $wali2->id, 'tipe' => 'test', 'judul' => 'B', 'pesan' => 'y']);
    $this->actingAs($wali1)->putJson("/api/wali/notifikasi/{$n->id}/read")->assertStatus(404);
});

test('mark read and mark all read', function (): void {
    $wali = User::factory()->wali()->create();
    $n1 = Notifikasi::create(['user_id' => $wali->id, 'tipe' => 'test', 'judul' => 'A', 'pesan' => 'x']);
    $n2 = Notifikasi::create(['user_id' => $wali->id, 'tipe' => 'test', 'judul' => 'B', 'pesan' => 'y']);

    $this->actingAs($wali)->getJson('/api/wali/notifikasi/unread-count')->assertOk()->assertJsonPath('data.count', 2);
    $this->actingAs($wali)->putJson("/api/wali/notifikasi/{$n1->id}/read")->assertOk();
    $this->actingAs($wali)->getJson('/api/wali/notifikasi/unread-count')->assertOk()->assertJsonPath('data.count', 1);
    $this->actingAs($wali)->putJson('/api/wali/notifikasi/read-all')->assertOk();
    $this->actingAs($wali)->getJson('/api/wali/notifikasi/unread-count')->assertOk()->assertJsonPath('data.count', 0);
});
