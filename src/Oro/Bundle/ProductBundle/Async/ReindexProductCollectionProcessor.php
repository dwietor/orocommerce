<?php

namespace Oro\Bundle\ProductBundle\Async;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Model\Exception\InvalidArgumentException;
use Oro\Bundle\ProductBundle\Model\SegmentMessageFactory;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Provider\SegmentSnapshotDeltaProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractIndexer;
use Oro\Bundle\WebsiteSearchBundle\Engine\AsyncIndexer;
use Oro\Bundle\WebsiteSearchBundle\Engine\AsyncMessaging\ReindexMessageGranularizer;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Psr\Log\LoggerInterface;

/**
 * MQ Processor that dispatches search reindexation event for added or removed products from product collection.
 */
class ReindexProductCollectionProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    /**
     * @var JobRunner
     */
    private $jobRunner;

    /**
     * @var MessageProducerInterface
     */
    private $producer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ReindexMessageGranularizer
     */
    private $reindexMessageGranularizer;

    /**
     * @var SegmentMessageFactory
     */
    private $messageFactory;

    /**
     * @var SegmentSnapshotDeltaProvider
     */
    private $segmentSnapshotDeltaProvider;

    /**
     * @param JobRunner $jobRunner
     * @param MessageProducerInterface $producer
     * @param LoggerInterface $logger
     * @param ReindexMessageGranularizer $reindexMessageGranularizer
     * @param SegmentMessageFactory $messageFactory
     * @param SegmentSnapshotDeltaProvider $segmentSnapshotDeltaProvider
     */
    public function __construct(
        JobRunner $jobRunner,
        MessageProducerInterface $producer,
        LoggerInterface $logger,
        ReindexMessageGranularizer $reindexMessageGranularizer,
        SegmentMessageFactory $messageFactory,
        SegmentSnapshotDeltaProvider $segmentSnapshotDeltaProvider
    ) {
        $this->jobRunner = $jobRunner;
        $this->producer = $producer;
        $this->logger = $logger;
        $this->reindexMessageGranularizer = $reindexMessageGranularizer;
        $this->messageFactory = $messageFactory;
        $this->segmentSnapshotDeltaProvider = $segmentSnapshotDeltaProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        try {
            $body = JSON::decode($message->getBody());
            $segment = $this->messageFactory->getSegmentFromMessage($body);
            $websiteIds = $this->messageFactory->getWebsiteIdsFromMessage($body);
            $jobName = $this->getUniqueJobName($segment, $websiteIds);
            $result = $this->jobRunner->runUnique(
                $message->getMessageId(),
                $jobName,
                function (JobRunner $jobRunner, Job $job) use ($segment, $websiteIds) {
                    $i = 0;
                    foreach ($this->getAllProductIdsForReindex($segment) as $batch) {
                        $reindexMsgData = $this->reindexMessageGranularizer->process(
                            [Product::class],
                            $websiteIds,
                            [AbstractIndexer::CONTEXT_ENTITIES_IDS_KEY => $batch]
                        );

                        foreach ($reindexMsgData as $msgData) {
                            $jobRunner->createDelayed(
                                sprintf('%s:reindex:%s', $job->getName(), ++$i),
                                function (JobRunner $jobRunner, Job $child) use ($msgData) {
                                    $msgData['jobId'] = $child->getId();
                                    $this->producer->send(AsyncIndexer::TOPIC_REINDEX, $msgData);
                                }
                            );
                        }
                    }

                    return true;
                }
            );

            return $result ? self::ACK : self::REJECT;
        } catch (InvalidArgumentException $e) {
            $this->logger->error(
                'Queue Message is invalid',
                [
                    'exception' => $e,
                    'message' => $message->getBody()
                ]
            );

            return self::REJECT;
        } catch (\Exception $e) {
            $this->logger->error(
                'Unexpected exception occurred during segment product collection reindexation',
                [
                    'message' => $message->getBody(),
                    'topic' => Topics::REINDEX_PRODUCT_COLLECTION_BY_SEGMENT,
                    'exception' => $e
                ]
            );

            return self::REJECT;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::REINDEX_PRODUCT_COLLECTION_BY_SEGMENT];
    }

    /**
     * @param Segment $segment
     * @return \Generator
     */
    private function getAllProductIdsForReindex(Segment $segment)
    {
        foreach ($this->segmentSnapshotDeltaProvider->getAddedEntityIds($segment) as $batch) {
            yield array_map('reset', $batch);
        }

        foreach ($this->segmentSnapshotDeltaProvider->getRemovedEntityIds($segment) as $batch) {
            yield array_map('reset', $batch);
        }
    }

    /**
     * @param Segment $segment
     * @param array $websiteIds
     * @return string
     */
    private function getUniqueJobName(Segment $segment, $websiteIds): string
    {
        sort($websiteIds);
        $jobKey = sprintf(
            '%s:%s',
            Topics::REINDEX_PRODUCT_COLLECTION_BY_SEGMENT,
            md5($segment->getDefinition()) . ':' . md5(implode($websiteIds))
        );

        return $jobKey;
    }
}
