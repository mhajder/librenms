<?php

namespace LibreNMS\Tests\Feature\Api;

use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Tests\DBTestCase;

final class PortsSearchApiTest extends DBTestCase
{
    use DatabaseTransactions;

    private Port $slashPort;
    private Port $zeroPort;

    protected function setUp(): void
    {
        parent::setUp();

        $device = Device::factory()->create();
        $this->slashPort = Port::factory()->for($device)->create(['ifName' => 'Ethernet1/1', 'ifDescr' => 'Ethernet1/1', 'ifAlias' => 'uplink', 'deleted' => 0]);
        $this->zeroPort = Port::factory()->for($device)->create(['ifName' => 'ge-0', 'ifDescr' => 'ge-0', 'ifAlias' => 'core', 'deleted' => 0]);
    }

    public function testSearchWithoutFieldContainingSlash(): void
    {
        $this->getJson('/api/v0/ports/search/Ethernet1%2F1', $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'ports')
            ->assertJsonPath('ports.0.port_id', $this->slashPort->port_id);
    }

    public function testSearchWithFieldContainingSlash(): void
    {
        $this->getJson('/api/v0/ports/search/ifName/Ethernet1%2F1', $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'ports')
            ->assertJsonPath('ports.0.port_id', $this->slashPort->port_id);
    }

    public function testSearchForZero(): void
    {
        $headers = $this->headers();

        $this->getJson('/api/v0/ports/search/ifName/0', $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'ports')
            ->assertJsonPath('ports.0.port_id', $this->zeroPort->port_id);

        $this->getJson('/api/v0/ports/search/0', $headers)
            ->assertStatus(200)
            ->assertJsonPath('ports.0.port_id', $this->zeroPort->port_id);
    }

    public function testExistingForms(): void
    {
        $headers = $this->headers();

        $this->getJson('/api/v0/ports/search/uplink', $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'ports')
            ->assertJsonPath('ports.0.port_id', $this->slashPort->port_id);

        $this->getJson('/api/v0/ports/search/ifAlias,ifName/core', $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'ports')
            ->assertJsonPath('ports.0.port_id', $this->zeroPort->port_id);

        $this->getJson('/api/v0/ports/search/notAColumn/core', $headers)
            ->assertStatus(400);
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
