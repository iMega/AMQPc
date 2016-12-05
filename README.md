# AMQP C for Codeception

[![CircleCI](https://circleci.com/gh/imega/codeception-amqpc.svg?style=svg)](https://circleci.com/gh/imega/codeception-amqpc)

## Usage

Declare queue

```
$I = new FunctionalTester($scenario);
$I->wantTo('Declare queue');
$I->declareQueueService('event.task');
```
