<?php
namespace byTorsten\GraphQL\SubscriptionsAdaptor\Command;

use byTorsten\GraphQL\Subscriptions\SubscriptionOptions;
use byTorsten\GraphQL\SubscriptionsAdaptor\DebugSubscriptionOptionsWrapper;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Wwwision\GraphQL\SchemaService;
use React\EventLoop\Factory as EventLoopFactory;
use byTorsten\GraphQL\Subscriptions\PubSubInterface;
use byTorsten\GraphQL\Subscriptions\Service\SubscriptionServer;

class GraphQLCommandController extends CommandController
{
    /**
     * @Flow\InjectConfiguration(package="Wwwision.GraphQL", path="endpoints")
     * @var array
     */
    protected $endpoints;

    /**
     * @Flow\Inject
     * @var PubSubInterface
     */
    protected $pubSub;

    /**
     * @Flow\Inject
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * @param int $port
     * @param string $host
     * @param string|null $endpoint
     * @param bool $verbose
     */
    public function subscriptionsServerCommand(int $port, string $host = 'localhost', string $endpoint = null, bool $verbose = false): void
    {
        $loop = EventLoopFactory::create();

        $server = new SubscriptionServer($loop, $port, $host);
        $endpoints = $endpoint === null ? array_keys($this->endpoints) : [$endpoint];

        foreach ($endpoints as $endpoint) {
            $configuration = $this->endpoints[$endpoint] ?? null;
            if ($configuration === null) {
                throw new \InvalidArgumentException(sprintf('Missing endpoint configuration for "%s"', $endpoint));
            }

            if (isset($configuration['subscriptionOptions'])) {
                $options = $this->objectManager->get($configuration['subscriptionOptions']);
            } else {
                $options = new SubscriptionOptions();
            }

            if ($verbose === true) {
                $options = new DebugSubscriptionOptionsWrapper($options);
            }

            $schema = $this->schemaService->getSchemaForEndpoint($endpoint);

            $server->addEndpoint($endpoint, $schema, $options);
            $this->outputFormatted('Subscription endpoint <success>%s</success> configured', [$server->getUri() . '/' . $endpoint]);
        }

        $this->pubSub->setup($loop)->then(function () use ($server) {
           $this->outputFormatted('Configured pubSub <success>%s</success>', [get_class($this->pubSub)]);
        }, function (\Throwable $error) use ($loop) {
            $this->outputFormatted('Failed to configure pubSub "%s":', [get_class($this->pubSub)]);
            $this->outputFormatted('<error>%s</error>', [$error->getMessage()]);
            $loop->stop();
            die();
        });

        $this->outputFormatted('Subscription server running at <success>%s</success>', [$server->getUri()]);

        $loop->run();
    }
}
