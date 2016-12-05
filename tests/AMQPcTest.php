<?php

namespace iMega;

use Codeception\Module\AMQPc;
use Rabman\ResourceFactory;

class AMQPcTest extends \PHPUnit_Framework_TestCase
{
    const EXCHANGE = 'example.exchange';
    const QUEUE = 'example.queue';

    protected $config = [
        'host'            => 'rabbit_host',
        'port'            => 5672,
        'login'           => 'guest',
        'password'        => 'guest',
        'vhost'           => '/',
        'read_timeout'    => 10,
        'write_timeout'   => 10,
        'connect_timeout' => 1,
        'attempts'        => 5,
        'wait_connect'    => 1,
        'services'        => [
            'logger' => [
                'name' => 'amq.direct',
                'type' => 'direct',
            ]
        ],
        'bindings'        => [
            'logger' => [
                'name'      => 'service.logger',
                'flags'     => 2,
                'arguments' => [],
                'exchanges' => [
                    'amq.direct' => 'routing.key',
                ]
            ],
        ],
        'apiport'  => 15672,
    ];
    /**
     * @var AMQPc
     */
    protected $module;

    /**
     * @var \AMQPConnection
     */
    protected $connection;

    /**
     * @var \Rabman\ResourceFactory
     */
    protected $rabman;

    public function setUp()
    {
        $this->module = new AMQPc(\Codeception\Util\Stub::make(\Codeception\Lib\ModuleContainer::class));
        $this->module->_setConfig($this->config);
        $this->module->_initialize();
        $this->connection = $this->module->connection;
        $this->rabman = new ResourceFactory([
            'base_uri' => 'http://' . $this->config['host'] . ':' . $this->config['apiport'],
            'auth' => [
                $this->config['login'],
                $this->config['password']
            ],
        ]);
    }

    public function tearDown()
    {
        $this->connection->disconnect();
    }

    public function testInitialize()
    {
        $this->assertTrue($this->module->connection->isConnected());
    }

    public function testCheckExistsExchange()
    {
        $this->rabman->exchanges(self::EXCHANGE)
            ->vhost()
            ->create(['type' => AMQP_EX_TYPE_DIRECT]);

        $this->module->checkExistsExchange(self::EXCHANGE);

        $this->rabman->exchanges(self::EXCHANGE)->vhost()->delete();
    }

    public function testDeclareQueueService()
    {
        $this->module->declareQueueService('logger');

        $result = $this->rabman->queues('service.logger')->vhost();
        $queues = [];
        foreach ($result as $item) {
            $queues[] = $item;
        }

        $this->assertSame(['service.logger'], array_column($queues, 'name'));

        $this->rabman->queues('service.logger')->vhost()->delete();
    }
}
