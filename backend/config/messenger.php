<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Message\ImportMessage;
use App\Message\ImportMessageHandler;
use App\Messenger\StopWorkerOnClosedEntityManagerListener;
use App\Messenger\TransportLocator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function DI\factory;

/*
 * Symfony Messenger без фреймворка Symfony: шина, транспорт AMQP (RabbitMQ) и команда воркера.
 * Сообщение ImportMessage уходит в транспорт "async"; воркер `messenger:consume async` забирает его
 * и вызывает ImportMessageHandler.
 */
return [
    'messenger.transports' => factory(static fn(Settings $s): TransportLocator => new TransportLocator([
        // in-memory:// — для тестов: сообщения остаются в памяти, RabbitMQ не нужен.
        'async' => static fn() => str_starts_with($s->messengerDsn, 'in-memory://')
            ? new InMemoryTransport()
            : (new AmqpTransportFactory())->createTransport($s->messengerDsn, [], new PhpSerializer()),
    ])),

    // Повторы при сбое обработчика: 3 попытки с паузой 1 с, 2 с, 4 с.
    'messenger.retry_strategies' => factory(static fn(): TransportLocator => new TransportLocator([
        'async' => static fn() => new MultiplierRetryStrategy(maxRetries: 3, delayMilliseconds: 1000, multiplier: 2),
    ])),

    MessageBusInterface::class => factory(static function (ContainerInterface $c): MessageBusInterface {
        /** @var ContainerInterface $transports */
        $transports = $c->get('messenger.transports');

        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator([ImportMessage::class => ['async']], $transports)),
            new HandleMessageMiddleware(new HandlersLocator([
                // Обработчик создаётся лениво: API, который только отправляет сообщения, его не собирает.
                ImportMessage::class => [static fn(ImportMessage $m) => $c->get(ImportMessageHandler::class)($m)],
            ])),
        ]);
    }),

    ConsumeMessagesCommand::class => factory(static function (ContainerInterface $c): ConsumeMessagesCommand {
        /** @var ContainerInterface $transports */
        $transports = $c->get('messenger.transports');
        /** @var ContainerInterface $retries */
        $retries = $c->get('messenger.retry_strategies');
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);
        /** @var MessageBusInterface $bus */
        $bus = $c->get(MessageBusInterface::class);

        $events = new EventDispatcher();
        $events->addSubscriber(new SendFailedMessageForRetryListener($transports, $retries, $logger));
        $events->addSubscriber($c->get(StopWorkerOnClosedEntityManagerListener::class));

        return new ConsumeMessagesCommand(
            new RoutableMessageBus(new TransportLocator([]), $bus),
            $transports,
            $events,
            $logger,
            ['async'],
        );
    }),
];
