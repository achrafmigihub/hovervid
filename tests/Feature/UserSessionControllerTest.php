<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Session;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class UserSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Test that a user can view their sessions.
     *
     * @return void
     */
    public function test_user_can_view_own_sessions()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        // Create some sample sessions for the user
        $session1 = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser 1',
            'last_activity' => now()->timestamp,
        ]);
        
        $session2 = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Browser 2',
            'last_activity' => now()->timestamp,
        ]);
        
        $response = $this->getJson('/api/sessions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'sessions',
                'current_session_id'
            ]);
            
        $sessions = $response->json('sessions');
        $this->assertCount(2, $sessions);
    }

    /**
     * Test that a user can revoke a specific session.
     *
     * @return void
     */
    public function test_user_can_revoke_specific_session()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        // Create a sample session for the user
        $session = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser',
            'last_activity' => now()->timestamp,
        ]);
        
        $response = $this->deleteJson('/api/sessions/' . $session->id);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Session revoked successfully'
            ]);
            
        $this->assertDatabaseHas('sessions', [
            'id' => $session->id,
            'is_active' => false
        ]);
    }

    /**
     * Test that a user cannot revoke another user's session.
     *
     * @return void
     */
    public function test_user_cannot_revoke_other_user_session()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Sanctum::actingAs($user1);
        
        // Create a sample session for user2
        $session = Session::createNewSession([
            'user_id' => $user2->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser',
            'last_activity' => now()->timestamp,
        ]);
        
        $response = $this->deleteJson('/api/sessions/' . $session->id);
        
        $response->assertStatus(404);
        
        $this->assertDatabaseHas('sessions', [
            'id' => $session->id,
            'is_active' => true
        ]);
    }

    /**
     * Test that a user can refresh their current session.
     *
     * @return void
     */
    public function test_user_can_refresh_current_session()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        // Create current session
        $sessionId = 'test_session_' . uniqid();
        $session = Session::create([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser',
            'last_activity' => now()->subMinutes(30)->timestamp,
            'expires_at' => now()->addHour(),
            'is_active' => true
        ]);
        
        // Mock the session ID
        $this->withSession(['_token' => 'test-token']);
        session()->setId($sessionId);
        
        $response = $this->postJson('/api/sessions/current/refresh');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'session_id',
                'expires_at'
            ]);
            
        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'is_active' => true
        ]);
        
        $updatedSession = Session::find($sessionId);
        $this->assertTrue($updatedSession->last_activity > $session->last_activity);
    }

    /**
     * Test that a user can revoke all other sessions.
     *
     * @return void
     */
    public function test_user_can_revoke_other_sessions()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        // Create current session
        $currentSessionId = 'current_session_' . uniqid();
        Session::create([
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Current Browser',
            'last_activity' => now()->timestamp,
            'expires_at' => now()->addHour(),
            'is_active' => true
        ]);
        
        // Create other sessions
        $otherSession1 = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Other Browser 1',
            'last_activity' => now()->timestamp,
        ]);
        
        $otherSession2 = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.3',
            'user_agent' => 'Other Browser 2',
            'last_activity' => now()->timestamp,
        ]);
        
        // Mock the session ID
        $this->withSession(['_token' => 'test-token']);
        session()->setId($currentSessionId);
        
        $response = $this->deleteJson('/api/sessions/other');
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'All other sessions revoked successfully'
            ]);
            
        $this->assertDatabaseHas('sessions', [
            'id' => $currentSessionId,
            'is_active' => true
        ]);
        
        $this->assertDatabaseHas('sessions', [
            'id' => $otherSession1->id,
            'is_active' => false
        ]);
        
        $this->assertDatabaseHas('sessions', [
            'id' => $otherSession2->id,
            'is_active' => false
        ]);
    }

    /**
     * Test that a user can view their session statistics.
     *
     * @return void
     */
    public function test_user_can_view_session_stats()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        
        // Create sessions for the user
        Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser 1',
            'last_activity' => now()->timestamp,
        ]);
        
        Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Test Browser 2',
            'last_activity' => now()->timestamp,
        ]);
        
        // Create an inactive session
        $inactiveSession = Session::createNewSession([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.3',
            'user_agent' => 'Test Browser 3',
            'last_activity' => now()->timestamp,
        ]);
        
        $inactiveSession->is_active = false;
        $inactiveSession->save();
        
        $response = $this->getJson('/api/sessions/stats');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_active_sessions',
                'total_inactive_sessions',
                'unique_devices',
                'recent_activity'
            ]);
            
        $data = $response->json();
        $this->assertEquals(2, $data['total_active_sessions']);
        $this->assertEquals(1, $data['total_inactive_sessions']);
    }
} 
