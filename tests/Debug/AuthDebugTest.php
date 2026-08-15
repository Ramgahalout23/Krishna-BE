<?php

namespace Tests\Debug;

use Tests\TestCase;
use App\Models\User;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Focused investigation into token lifecycle, logout/reauth behavior.
 */
class AuthDebugTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';

    /** @test */
    public function investigate_login_logout_reauth_flow()
    {
        // This replicates the EXACT flow from the failing test
        echo "=== EXACT FLOW REPLICATION ===\n\n";

        // Register
        $reg = $this->postJson("{$this->apiPrefix}/auth/register", [
            'first_name' => 'John', 'last_name' => 'Doe',
            'email' => 'john.doe@example.com', 'password' => 'Secure@123',
        ]);
        $token = $reg->json('data.token');
        echo "1. Register: status={$reg->status()}, token=" . substr($token ?? 'null', 0, 20) . "...\n";

        // Get profile
        $me1 = $this->withHeader('Authorization', "Bearer {$token}")->getJson("{$this->apiPrefix}/auth/me");
        echo "2. GET /me (initial): status={$me1->status()}\n";

        // Refresh token
        $refresh = $this->withHeader('Authorization', "Bearer {$token}")->postJson("{$this->apiPrefix}/auth/refresh-token");
        $newToken = $refresh->json('data.token');
        echo "3. Refresh token: status={$refresh->status()}, new_token=" . substr($newToken ?? 'null', 0, 20) . "...\n";
        
        // Check tokens in DB after refresh
        $tokensAfterRefresh = PersonalAccessToken::count();
        echo "   Tokens in DB after refresh: $tokensAfterRefresh\n";

        // Login
        $login = $this->postJson("{$this->apiPrefix}/auth/login", ['email' => 'john.doe@example.com', 'password' => 'Secure@123']);
        $loginToken = $login->json('data.token');
        echo "4. Login: status={$login->status()}, token=" . substr($loginToken ?? 'null', 0, 20) . "...\n";

        // Check tokens in DB after login
        $tokensAfterLogin = PersonalAccessToken::count();
        echo "   Tokens in DB after login: $tokensAfterLogin\n";

        // CHANGE PASSWORD using the login token
        $changePass = $this->withHeader('Authorization', "Bearer {$loginToken}")
            ->postJson("{$this->apiPrefix}/auth/change-password", [
                'current_password' => 'Secure@123',
                'new_password' => 'NewSecure@456',
            ]);
        echo "5. Change password: status={$changePass->status()}, body=" . json_encode($changePass->json()) . "\n";

        // Check tokens in DB after change password
        $tokensAfterChangePass = PersonalAccessToken::count();
        echo "   Tokens in DB after change password: $tokensAfterChangePass\n";

        // Logout using the login token
        $logout = $this->withHeader('Authorization', "Bearer {$loginToken}")
            ->postJson("{$this->apiPrefix}/auth/logout");
        echo "6. Logout: status={$logout->status()}\n";

        // Check tokens in DB after logout
        $tokensAfterLogout = PersonalAccessToken::count();
        echo "   Tokens in DB after logout: $tokensAfterLogout\n";

        // Try to use old token
        $me2 = $this->withHeader('Authorization', "Bearer {$loginToken}")
            ->getJson("{$this->apiPrefix}/auth/me");
        echo "7. GET /me (after logout, old token): status={$me2->status()}, body=" . json_encode($me2->json()) . "\n\n";

        // Login with new password (should work if password was changed)
        $login2 = $this->postJson("{$this->apiPrefix}/auth/login", ['email' => 'john.doe@example.com', 'password' => 'NewSecure@456']);
        echo "8. Login (new password): status={$login2->status()}\n";

        // Login with wrong password (should NOT work)
        $login3 = $this->postJson("{$this->apiPrefix}/auth/login", ['email' => 'john.doe@example.com', 'password' => 'WrongPassword@123']);
        echo "9. Login (wrong password): status={$login3->status()}\n";

        // Login with non-existent user
        $login4 = $this->postJson("{$this->apiPrefix}/auth/login", ['email' => 'nonexist@test.com', 'password' => 'Secure@123']);
        echo "10. Login (non-existent user): status={$login4->status()}\n";

        // Duplicate registration
        $dupReg = $this->postJson("{$this->apiPrefix}/auth/register", [
            'first_name' => 'John', 'last_name' => 'Doe',
            'email' => 'john.doe@example.com', 'password' => 'Secure@123',
        ]);
        echo "11. Duplicate registration: status={$dupReg->status()}\n";

        echo "\n=== SUMMARY ===\n";
        echo "Test completed. Check output above.\n";
        
        // Keep this as info-only, don't fail
        $this->assertTrue(true);
    }
}
