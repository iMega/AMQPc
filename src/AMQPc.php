<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleException;
use Codeception\Util\Debug;
use Rabman\ResourceFactory;

class AMQPc extends \Codeception\Module
{
    protected $requiredFields = [
        'host',
        'login',
        'password',
    ];

    protected $config = [
        'host'     => 'locahost',
        'login'    => 'guest',
        'password' => 'guest',
        'port'     => 5672,
        'apiport'  => 15672,
        'vhost'    => '/',
        'cleanup'  => true,
    ];

    /**
     * @var \Rabman\ResourceFactory
     */
    public $service;

    /**
     * @var \AMQPConnection
     */
    public $connection;

    /**
     * @var \AMQPChannel
     */
    protected $channel;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * @var array
     */
    protected $bindings = [];

    public function _initialize()
    {
        if (!class_exists('GuzzleHttp\Client')) {
            throw new ModuleException($this, 'Guzzle is not installed. Please install `guzzlehttp/guzzle` with composer');
        }

        $this->service = new ResourceFactory([
            'base_uri' => 'http://' . $this->config['host'] . ':' . $this->config['apiport'],
            'auth' => [
                $this->config['login'],
                $this->config['password']
            ],
        ]);

        $this->services = $this->config['services'];
        $this->bindings = $this->config['bindings'];

        $credentials = [
            'host'     => $this->config['host'],
            'port'     => $this->config['port'],
            'login'    => $this->config['login'],
            'password' => $this->config['password'],
            'vhost'    => $this->config['vhost'],
        ];

        $this->connection = new \AMQPConnection($credentials);
        try {
            $this->connection->connect();
        } catch (\AMQPConnectionException $e) {
            throw new ModuleException(__CLASS__, $e->getMessage());
        }
    }

    public function _cleanup() {
    }

    // HOOK: before each suite
    public function _beforeSuite($settings = array()) {
    }

    // HOOK: after suite
    public function _afterSuite() {
    }

    // HOOK: before each step
    public function _beforeStep(\Codeception\Step $step) {
    }

    // HOOK: after each  step
    public function _afterStep(\Codeception\Step $step) {
    }

    // HOOK: before test
    public function _before(\Codeception\TestCase $test) {
    }

    // HOOK: after test
    public function _after(\Codeception\TestCase $test) {
    }

    // HOOK: on fail
    public function _failed(\Codeception\TestCase $test, $fail) {
    }

    public function checkExistsExchange($exchange)
    {
        $result = $this->service->exchanges()->columns(['name']);

        $exchanges = count($result);
        $this->assertTrue($exchanges > 0);

        $exchanges = [];
        foreach ($result as $item) {
            $exchanges[] = $item;
        }

        $this->assertContains($exchange, array_column($exchanges, 'name'));
    }

    public function checkExistsConsumerQueue($queue)
    {
        $result = $this->service->consumers()->columns(['queue.name']);

        $consumers = count($result);
        $this->assertTrue($consumers > 0);

        $consumers = [];
        foreach ($result as $item) {
            $consumers[] = $item['queue'];
        }

        $this->assertContains($queue, array_column($consumers, 'name'));
    }

    public function pushToExchange($exchange, $message, $routing_key = null)
    {
        $exchange = $this->getExchange($exchange);
        $exchange->publish($message, $routing_key);
    }

    public function castToService($name, $message, $attributes = [])
    {
        $options  = $this->getOptionsServices($name);
        $exchange = $this->getExchange($name);
        $attrs    = !empty($attributes) ? $attributes : $options['attributes'];
        $result   = $exchange->publish($message, $options['routing_key'], $options['flagsMsg'], $attrs);

        $this->assertTrue($result);
    }

    public function callToService($name, $message, $attributes = [], $callback)
    {
        $options  = $this->getOptionsServices($name);
        $exchange = $this->getExchange($name);
        $queue    = $this->bindQueue($name);
        $attrs    = !empty($attributes) ? $attributes : $options['attributes'];

        $attrs['correlation_id'] = microtime();
        $attrs['reply_to']       = $queue->getName();

        $result = $exchange->publish($message, $options['routing_key'], $options['flagsMsg'], $attrs);

        $this->assertTrue($result);

        $queue->consume($callback, AMQP_AUTOACK);
    }

    public function declareQueueService($name)
    {
        $queue = $this->bindQueue($name);

        $this->assertTrue($queue instanceof \AMQPQueue);
    }

    public function listenService($name, $callback)
    {
        $exchange = $this->getExchange($name);

        $q = $this->bindQueue($name);
        $q->consume(function(\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($exchange, $callback) {

            $exchange->publish($callback($envelope->getBody()), $envelope->getReplyTo(), AMQP_MANDATORY, [
                'content_encoding' => 'base64',
                'correlation_id' => $envelope->getCorrelationId(),
            ]);

            return false;
        }, AMQP_AUTOACK);
    }

    public function listeCastService($name, $callback)
    {
        $exchange = $this->getExchange($name);

        $q = $this->bindQueue($name);
        $q->consume(function(\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($exchange, $callback) {
            $callback($envelope->getBody());

            return false;
        }, AMQP_AUTOACK);
    }

    /**
     * @return \AMQPConnection
     *
     * @throws ModuleException
     */
    protected function getConnection()
    {
        if (null === $this->connection) {
            try {
                $this->connection = new \AMQPConnection($this->config);
                $this->connection->connect();
            } catch (\AMQPConnectionException $e) {
                throw new ModuleException(__CLASS__, $e->getMessage());
            }
        }

        return $this->connection;
    }

    /**
     * @return \AMQPChannel
     */
    protected function getChannel()
    {
        if ($this->channel === null) {
            $connection = $this->getConnection();
            $this->channel = new \AMQPChannel($connection);
        }

        return $this->channel;
    }

    /**
     * @param string $name
     *
     * @return \AMQPExchange
     */
    protected function getExchange($name)
    {
        $options = $this->getOptionsServices($name);
        $exchange = new \AMQPExchange($this->getChannel());
        $exchange->setName($options['name']);
        $exchange->setType($options['type']);
        $exchange->setFlags($options['flags']);
        $exchange->setArguments($options['arguments']);
        if ($options['declare']) {
            $exchange->declareExchange();
        }

        return $exchange;
    }

    /**
     * @param string $name
     *
     * @return \AMQPQueue
     */
    protected function bindQueue($name)
    {
        $options = $this->getOptionsBindings($name);

        $queue = new \AMQPQueue($this->getChannel());
        if (!empty($options['name'])) {
            $queue->setName($options['name']);
        }
        $queue->setFlags($options['flags']);
        $queue->setArguments($options['arguments']);
        $queue->declareQueue();

        foreach ($options['exchanges'] as $exchangeName => $routingKey) {
            $queue->bind($exchangeName, $routingKey);
        }

        return $queue;
    }

    /**
     * @param string $name Config of exchange
     *
     * @return array
     */
    protected function getOptionsServices($name) {
        $options = $this->services[$name];

        return [
            'name'      => $options['name'],
            'type'      => $options['type'],
            'flags'     => isset($options['flags']) ? $options['flags'] : AMQP_NOPARAM,
            'arguments' => !empty($options['arguments']) ? $options['arguments'] : [],
            'declare'   => isset($options['declare']) && (true === $options['declare']),
            'flagsMsg'  => isset($options['flagsMsg']) ? $options['flagsMsg'] : AMQP_NOPARAM,
            'attributes' => !empty($options['attributes']) ? $options['attributes'] : [],
            'routing_key' => isset($options['routing_key']) ? $options['routing_key'] : null,
        ];
    }

    /**
     * @param string $name Config of exchange
     *
     * @return array
     */
    protected function getOptionsBindings($name) {
        $options = $this->bindings[$name];

        return [
            'name'        => isset($options['name']) ? $options['name'] : '',
            'flags'       => isset($options['flags']) ? $options['flags'] : AMQP_NOPARAM,
            'arguments'   => !empty($options['arguments']) ? $options['arguments'] : [],
            'routing_key' => isset($options['routing_key']) ? $options['routing_key'] : null,
            'exchanges'   => !empty($options['exchanges']) ? $options['exchanges'] : [],
        ];
    }
}
