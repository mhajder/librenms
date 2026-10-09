<?php

namespace LibreNMS\Tests\Feature\Api;

use App\Models\AlertFault;
use App\Models\AlertRule;
use App\Models\AlertTemplate;
use App\Models\Bill;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Enum\AlertState;
use LibreNMS\Tests\DBTestCase;

/**
 * Looking up a missing object by ID is answered with 404, not with an empty successful result.
 */
final class NotFoundByIdApiTest extends DBTestCase
{
    use DatabaseTransactions;

    public function testAlertById(): void
    {
        $headers = $this->headers();
        $fault = $this->createFault(AlertState::ACTIVE);

        $this->getJson("/api/v0/alerts/$fault->id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('alerts.0.id', $fault->id);

        // exists, but not in the default state filter (active): still found, just filtered out
        $recovered = $this->createFault(AlertState::RECOVERED);
        $this->getJson("/api/v0/alerts/$recovered->id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('count', 0);

        $this->getJson('/api/v0/alerts/999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Alert 999999 does not exist');

        // a non-numeric ID used to be ignored and list every alert
        $this->getJson('/api/v0/alerts/abc', $headers)
            ->assertStatus(400);
    }

    public function testAckAndUnmuteMissingAlert(): void
    {
        $headers = $this->headers();

        $this->putJson('/api/v0/alerts/999999', ['note' => 'x', 'until_clear' => false], $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Alert 999999 does not exist');

        $this->putJson('/api/v0/alerts/unmute/999999', ['note' => 'x'], $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Alert 999999 does not exist');
    }

    public function testAlertRuleById(): void
    {
        $headers = $this->headers();
        $rule = AlertRule::factory()->create();

        $this->getJson("/api/v0/rules/$rule->id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('rules.0.id', $rule->id);

        $this->getJson('/api/v0/rules/999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Alert rule 999999 does not exist');

        $this->getJson('/api/v0/rules', $headers)
            ->assertStatus(200);
    }

    public function testAlertTemplateById(): void
    {
        $headers = $this->headers();
        $template = AlertTemplate::factory()->create();

        $this->getJson("/api/v0/alert_templates/$template->id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('alert_templates.0.id', $template->id);

        $this->getJson('/api/v0/alert_templates/999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Alert template 999999 does not exist');
    }

    public function testBillById(): void
    {
        $headers = $this->headers();
        $bill = Bill::factory()->create();

        $this->getJson("/api/v0/bills/$bill->bill_id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('bills.0.bill_id', $bill->bill_id);

        // a bill without history has no previous period; that is not a missing bill
        $this->getJson("/api/v0/bills/$bill->bill_id?period=previous", $headers)
            ->assertStatus(200)
            ->assertJsonPath('count', 0);

        $this->getJson('/api/v0/bills/999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Bill 999999 does not exist');

        // a non-numeric ID used to be ignored and list every bill
        $this->getJson('/api/v0/bills/abc', $headers)
            ->assertStatus(400);
    }

    public function testPortById(): void
    {
        $headers = $this->headers();
        $port = Port::factory()->for(Device::factory())->create();

        $this->getJson("/api/v0/ports/$port->port_id", $headers)
            ->assertStatus(200)
            ->assertJsonPath('port.0.port_id', $port->port_id);

        $this->getJson('/api/v0/ports/999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Port 999999 does not exist');
    }

    private function createFault(int $state): AlertFault
    {
        $device = Device::factory()->create();
        $rule = AlertRule::factory()->create();

        return AlertFault::create([
            'rule_id' => $rule->id,
            'device_id' => $device->device_id,
            'entity_key' => (string) $device->device_id,
            'state' => $state,
            'open' => 1,
            'alerted' => 0,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();

        return ['X-Auth-Token' => $user->createToken('test')->plainTextToken];
    }
}
