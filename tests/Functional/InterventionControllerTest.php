<?php

namespace App\Tests\Functional;

use App\Entity\Antenna;
use App\Enum\AntennaStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InterventionControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Isolation simple entre tests : on repart d'une base vide pour ces
        // deux tables à chaque test.
        $connection = $this->em->getConnection();
        $connection->executeStatement('TRUNCATE TABLE intervention, antenna RESTART IDENTITY CASCADE');
    }

    private function createAntenna(string $name = 'Antenne Paris 1', string $city = 'Paris'): Antenna
    {
        $antenna = new Antenna($name, $city, AntennaStatus::UP);
        $this->em->persist($antenna);
        $this->em->flush();

        return $antenna;
    }

    public function testCreatingAnInterventionSwitchesAntennaToDown(): void
    {
        $client = $this->client;
        $antenna = $this->createAntenna();

        $client->request(
            'POST',
            '/api/interventions',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_API_KEY' => $_ENV['API_KEY'],
            ],
            content: json_encode([
                'antenna_id' => $antenna->getId(),
                'description' => 'Panne secteur suite orage.',
                'technician_identity' => 'mkhaled',
                'priority' => 'HIGH',
            ])
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('HIGH', $payload['priority']);
        self::assertSame('DOWN', $payload['antenna_status']);
        self::assertNull($payload['ended_at']);

        $this->em->refresh($antenna);
        self::assertSame(AntennaStatus::DOWN, $antenna->getStatus());
    }

    public function testCreatingASecondActiveInterventionOnSameAntennaFailsWith409(): void
    {
        $client = $this->client;
        $antenna = $this->createAntenna();

        $payload = static fn() => json_encode([
            'antenna_id' => $antenna->getId(),
            'description' => 'Intervention.',
            'technician_identity' => 'mkhaled',
            'priority' => 'MEDIUM',
        ]);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $_ENV['API_KEY'],
        ];

        // Première intervention : doit passer.
        $client->request('POST', '/api/interventions', server: $headers, content: $payload());
        self::assertResponseStatusCodeSame(201);

        // Deuxième intervention active sur la même antenne : doit être rejetée.
        $client->request('POST', '/api/interventions', server: $headers, content: $payload());
        self::assertResponseStatusCodeSame(409);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ACTIVE_INTERVENTION_EXISTS', $body['error']['code']);
    }

    public function testCreatingAnInterventionWithoutApiKeyFailsWith401(): void
    {
        $client = $this->client;
        $antenna = $this->createAntenna();

        $client->request(
            'POST',
            '/api/interventions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'antenna_id' => $antenna->getId(),
                'description' => 'Intervention.',
                'technician_identity' => 'mkhaled',
                'priority' => 'LOW',
            ])
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreatingAnInterventionWithInvalidPayloadFailsWith422(): void
    {
        $client = $this->client;

        $client->request(
            'POST',
            '/api/interventions',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_API_KEY' => $_ENV['API_KEY'],
            ],
            content: json_encode(['description' => 'Sans antenne ni priorité.'])
        );

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
    }

    public function testClosingAnInterventionSwitchesAntennaBackToUp(): void
    {
        $client = $this->client;
        $antenna = $this->createAntenna();

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $_ENV['API_KEY'],
        ];

        $client->request('POST', '/api/interventions', server: $headers, content: json_encode([
            'antenna_id' => $antenna->getId(),
            'description' => 'Panne.',
            'technician_identity' => 'mkhaled',
            'priority' => 'LOW',
        ]));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('PATCH', '/api/interventions/' . $created['id'] . '/close', server: $headers);
        self::assertResponseStatusCodeSame(200);

        $closed = json_decode($client->getResponse()->getContent(), true);
        self::assertNotNull($closed['ended_at']);
        self::assertSame('UP', $closed['antenna_status']);
    }

    public function testClosingAnAlreadyClosedInterventionFailsWith409(): void
    {
        $client = $this->client;
        $antenna = $this->createAntenna();

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => $_ENV['API_KEY'],
        ];

        $client->request('POST', '/api/interventions', server: $headers, content: json_encode([
            'antenna_id' => $antenna->getId(),
            'description' => 'Panne.',
            'technician_identity' => 'mkhaled',
            'priority' => 'LOW',
        ]));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('PATCH', '/api/interventions/' . $created['id'] . '/close', server: $headers);
        self::assertResponseStatusCodeSame(200);

        // Deuxième clôture de la même intervention : doit être rejetée.
        $client->request('PATCH', '/api/interventions/' . $created['id'] . '/close', server: $headers);
        self::assertResponseStatusCodeSame(409);
    }
}
