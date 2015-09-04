<?php

namespace Everlution\EmailBundle\Outbound\Mailer;

use DateTime;
use Everlution\EmailBundle\Entity\StorableOutboundMessage;
use Everlution\EmailBundle\Entity\StorableOutboundMessageInfo;
use Everlution\EmailBundle\Message\Outbound\UniqueOutboundMessage;
use Everlution\EmailBundle\Outbound\MailSystem\MailSystem;
use Everlution\EmailBundle\Message\Outbound\OutboundMessage;
use Everlution\EmailBundle\Message\Outbound\ProcessedOutboundMessage;
use Everlution\EmailBundle\Outbound\MailSystem\MailSystemException;
use Everlution\EmailBundle\Outbound\MailSystem\MailSystemResult;
use Everlution\EmailBundle\Support\MessageId\Generator as MessageIdGenerator;
use Everlution\EmailBundle\Transformer\OutboundMessageTransformer;

abstract class Mailer implements MailerInterface
{

    /** @var OutboundMessageTransformer[] */
    protected $messageTransformers = [];

    /** @var MessageIdGenerator */
    protected $messageIdGenerator;

    /** @var MailSystem */
    protected $mailSystem;


    /**
     * @param MessageIdGenerator $messageIdGenerator
     * @param MailSystem $mailSystem
     */
    public function __construct(MessageIdGenerator $messageIdGenerator, MailSystem $mailSystem)
    {
        $this->messageIdGenerator = $messageIdGenerator;
        $this->mailSystem = $mailSystem;
    }

    /**
     * @param OutboundMessageTransformer $transformer
     */
    public function addMessageTransformer(OutboundMessageTransformer $transformer)
    {
        $this->messageTransformers[] = $transformer;
    }

    /**
     * @param OutboundMessage $message
     * @return ProcessedOutboundMessage
     */
    protected function processMessage(OutboundMessage $message)
    {
        $this->transformMessage($message);

        $identifiableMessage = $this->convertToIdentifiableMessage($message);
        $storableMessage = new StorableOutboundMessage($identifiableMessage, $this->mailSystem->getMailSystemName());

        return new ProcessedOutboundMessage($identifiableMessage, $storableMessage);
    }

    /**
     * @param OutboundMessage $message
     */
    protected function transformMessage(OutboundMessage $message)
    {
        foreach ($this->messageTransformers as $transformer) {
            $transformer->transform($message);
        }
    }

    /**
     * @param OutboundMessage $message
     * @return UniqueOutboundMessage
     */
    protected function convertToIdentifiableMessage(OutboundMessage $message)
    {
        $newMessageId = $this->messageIdGenerator->generate();

        return new UniqueOutboundMessage($newMessageId, $message);
    }

    /**
     * @param ProcessedOutboundMessage $processedMessage
     * @throws MailSystemException
     */
    protected function sendProcessedMessage(ProcessedOutboundMessage $processedMessage)
    {
        $result = $this->mailSystem->sendMessage($processedMessage->getUniqueOutboundMessage());
        $this->handleMailSystemResult($result, $processedMessage);
    }

    /**
     * @param ProcessedOutboundMessage $processedMessage
     * @param DateTime $sendAt
     * @throws MailSystemException
     */
    protected function scheduleProcessedMessage(ProcessedOutboundMessage $processedMessage, DateTime $sendAt)
    {
        $result = $this->mailSystem->scheduleMessage($processedMessage->getUniqueOutboundMessage(), $sendAt);
        $this->handleMailSystemResult($result, $processedMessage);

        $processedMessage->getStorableMessage()->setScheduledSendTime($sendAt);
    }

    /**
     * @param MailSystemResult $result
     * @param ProcessedOutboundMessage $processedMessage
     */
    protected function handleMailSystemResult(MailSystemResult $result, ProcessedOutboundMessage $processedMessage)
    {
        $storableMessage = $processedMessage->getStorableMessage();

        foreach ($result->getMailSystemMessagesInfo() as $mailSystemMessageInfo) {
            $storableMessage->addMessageInfo(new StorableOutboundMessageInfo($storableMessage, $mailSystemMessageInfo));
        }
    }

}
