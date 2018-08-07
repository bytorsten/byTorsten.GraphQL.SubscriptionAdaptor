<?php
namespace byTorsten\GraphQL\SubscriptionsAdaptor;

use byTorsten\GraphQL\Subscriptions\Domain\Model\ConnectionContext;
use byTorsten\GraphQL\Subscriptions\Domain\Model\ExecutionParameters;
use byTorsten\GraphQL\Subscriptions\SubscriptionOptions;
use Neos\Flow\Cli\ConsoleOutput;
use Ratchet\ConnectionInterface;

class DebugSubscriptionOptionsWrapper extends SubscriptionOptions
{
    /**
     * @var SubscriptionOptions
     */
    protected $subscriptionOptions;

    /**
     * @var ConsoleOutput
     */
    protected $console;

    /**
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * @param SubscriptionOptions $subscriptionOptions
     */
    public function __construct(SubscriptionOptions $subscriptionOptions)
    {
        parent::__construct();
        $this->subscriptionOptions = $subscriptionOptions;
        $this->console = new ConsoleOutput();
        $this->clients = new \SplObjectStorage();
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param bool $newline
     */
    protected function debug(string $message, array $arguments = [], bool $newline = true)
    {
        if ($newline) {
            $this->console->outputLine();
        }
        $this->console->outputLine('<comment>[Subscription]</comment>   ' . vsprintf($message, $arguments));
    }

    /**
     * @param $payload
     * @param ConnectionInterface $socket
     * @param ConnectionContext $connectionContext
     * @return mixed|void
     */
    public function onConnect($payload, ConnectionInterface $socket, ConnectionContext $connectionContext)
    {
        $this->clients->attach($socket);
        $this->debug('New connection. Currently <success>%s</success> clients are connected', [$this->clients->count()]);

        parent::onConnect($payload, $socket, $connectionContext);
    }

    /**
     * @param ConnectionInterface $socket
     * @param ConnectionContext $connectionContext
     */
    public function onDisconnect(ConnectionInterface $socket, ConnectionContext $connectionContext)
    {
        $this->clients->detach($socket);
        $this->debug('Connection closed. Currently <success>%s</success> clients are connected', [$this->clients->count()]);

        parent::onDisconnect($socket, $connectionContext);
    }

    /**
     * @param array $message
     * @param ExecutionParameters $parameters
     * @param ConnectionInterface $socket
     * @return ExecutionParameters|\React\Promise\PromiseInterface
     */
    public function onOperation(array $message, ExecutionParameters $parameters, ConnectionInterface $socket)
    {
        $this->debug('Received operation request:');
        $this->debug('  Operation ID: <success>%s</success>', [$message['id'] ?? 'undefined'], false);
        $this->debug('  Operation Name: <success>%s</success>', [$parameters->getOperationName()], false);
        $this->debug('  Query: <success>%s</success>', [preg_replace("/\r|\n/", '', $parameters->getQuery())], false);

        $variables = $parameters->getVariables();
        $this->debug('  Variables: <success>%s</success>', [count($variables) > 0 ? '' : 'none'], false);
        foreach ($variables as $name => $value) {
            $this->debug('    <success>%s</success>: %s', [$name, $value], false);
        }

        return parent::onOperation($message, $parameters, $socket);
    }

    /**
     * @param ConnectionInterface $socket
     * @param string $opId
     */
    public function onOperationComplete(ConnectionInterface $socket, string $opId): void
    {
        $this->debug('Operation <success>%s</success> completed', [$opId]);
        parent::onOperationComplete($socket, $opId);
    }
}
