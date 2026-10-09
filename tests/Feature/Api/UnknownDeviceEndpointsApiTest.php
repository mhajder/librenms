<?php

namespace LibreNMS\Tests\Feature\Api;

use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Tests\DBTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An unknown device is answered with 404, not with an empty (or made up) successful result.
 */
final class UnknownDeviceEndpointsApiTest extends DBTestCase
{
    use DatabaseTransactions;

    /**
     * @return array<string, array{string}>
     */
    public static function deviceEndpoints(): array
    {
        return [
            'availability' => ['/api/v0/devices/{device}/availability'],
            'outages' => ['/api/v0/devices/{device}/outages'],
            'graphs' => ['/api/v0/devices/{device}/graphs'],
            'health' => ['/api/v0/devices/{device}/health'],
            'health type' => ['/api/v0/devices/{device}/health/device_temperature'],
            'health sensor' => ['/api/v0/devices/{device}/health/device_temperature/1'],
            'port security' => ['/api/v0/port_security/device/{device}'],
            'inventory' => ['/api/v0/inventory/{device}'],
            'inventory all' => ['/api/v0/inventory/{device}/all'],
            'arp' => ['/api/v0/resources/ip/arp/all?device={device}'],
        ];
    }

    #[DataProvider('deviceEndpoints')]
    public function testUnknownHostnameIsNotFound(string $uri): void
    {
        $this->getJson(str_replace('{device}', 'does-not-exist.example.com', $uri), $this->headers())
            ->assertStatus(404)
            ->assertJsonPath('message', 'Device does-not-exist.example.com does not exist');
    }

    #[DataProvider('deviceEndpoints')]
    public function testUnknownDeviceIdIsNotFound(string $uri): void
    {
        $this->getJson(str_replace('{device}', '999999', $uri), $this->headers())
            ->assertStatus(404)
            ->assertJsonPath('message', 'Device 999999 does not exist');
    }

    #[DataProvider('deviceEndpoints')]
    public function testKnownDeviceIsFound(string $uri): void
    {
        $device = Device::factory()->create();
        $headers = $this->headers();

        foreach ([$device->hostname, (string) $device->device_id] as $ref) {
            $this->getJson(str_replace('{device}', $ref, $uri), $headers)
                ->assertStatus(200)
                ->assertJsonPath('status', 'ok');
        }
    }

    public function testPortStatsForUnknownPortOrDevice(): void
    {
        $headers = $this->headers();
        $device = Device::factory()->create();
        $port = Port::factory()->for($device)->create(['ifName' => 'Ethernet1', 'deleted' => 0]);

        $this->getJson("/api/v0/devices/$device->hostname/ports/Ethernet1", $headers)
            ->assertStatus(200)
            ->assertJsonPath('port.port_id', $port->port_id);

        $this->getJson("/api/v0/devices/$device->hostname/ports/Ethernet9", $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', "Port Ethernet9 does not exist on device $device->hostname");

        $this->getJson('/api/v0/devices/does-not-exist.example.com/ports/Ethernet1', $headers)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Device does-not-exist.example.com does not exist');
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
