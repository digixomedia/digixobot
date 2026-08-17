<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_requires_authentication_and_authorized_admin_can_open_dashboard_and_customer_history(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $admin = User::create(['name' => 'digixostore', 'email' => 'digixomedia3@gmail.com', 'password' => 'test-password']);
        $customer = Customer::create([
            'telegram_id' => 999001, 'telegram_username' => 'customer', 'name' => 'Test Customer',
            'customer_number' => 'DXO-ADMIN-TEST', 'wallet_balance_paise' => 0, 'total_spend_paise' => 0,
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('DigiXO Store');
        $this->actingAs($admin)->get('/admin/customers/'.$customer->id.'/edit')->assertOk()->assertSee('Order history')->assertSee('Wallet history');
    }
}
