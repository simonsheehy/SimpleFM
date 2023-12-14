<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Client\ResultSet;

use DateTimeZone;
use Exception;
use SimpleXMLElement;
use Soliant\SimpleFM\Client\Exception\FileMakerException;
use Soliant\SimpleFM\Client\ResultSet\Exception\ParseException;
use Soliant\SimpleFM\Client\ResultSet\Exception\UnknownFieldException;
use Soliant\SimpleFM\Client\ResultSet\Transformer\ContainerTransformer;
use Soliant\SimpleFM\Client\ResultSet\Transformer\DateTimeTransformer;
use Soliant\SimpleFM\Client\ResultSet\Transformer\DateTransformer;
use Soliant\SimpleFM\Client\ResultSet\Transformer\NumberTransformer;
use Soliant\SimpleFM\Client\ResultSet\Transformer\TextTransformer;
use Soliant\SimpleFM\Client\ResultSet\Transformer\TimeTransformer;
use Soliant\SimpleFM\Collection\CollectionInterface;
use Soliant\SimpleFM\Collection\ItemCollection;
use Soliant\SimpleFM\Connection\Command;
use Soliant\SimpleFM\Connection\ConnectionInterface;

final class ResultSetClient implements ResultSetClientInterface
{
    const GRAMMAR_PATH = '/fmi/xml/fmresultset.xml';

    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @var callable[]
     */
    private $transformers;

    public function __construct(ConnectionInterface $connection, DateTimeZone $serverTimeZone)
    {
        $this->connection = $connection;
        $this->initializeTransformers($serverTimeZone);
    }

    public function execute(Command $command): CollectionInterface
    {
        $xml = $this->connection->execute($command, self::GRAMMAR_PATH);
        $errorCode = (int) $xml->error['code'];
        $dataSource = $xml->datasource;

        if ($errorCode === 8 || $errorCode === 401) {
            // "Empty result" or "No records match the request"
            return new ItemCollection([], 0);
        } elseif ($errorCode > 0) {
            throw FileMakerException::fromErrorCode($errorCode);
        }

        try {
            $metadata = $this->parseMetadata($xml->metadata[0]);
            $records = [];

            foreach ($xml->resultset[0]->record as $record) {
                $records[] = $this->parseRecord($record, $metadata);
            }
        } catch (UnknownFieldException $e) {
            throw UnknownFieldException::fromConcreteException(
                (string) $dataSource['database'],
                (string) $dataSource['table'],
                (string) $dataSource['layout'],
                $e
            );
        } catch (Exception $e) {
            throw ParseException::fromConcreteException(
                (string) $dataSource['database'],
                (string) $dataSource['table'],
                (string) $dataSource['layout'],
                $e
            );
        }

        return new ItemCollection($records, (int) $xml->resultset[0]['count']);
    }

    public function quoteString(string $string): string
    {
        return strtr($string, [
            '\\' => '\\\\',
            '=' => '\\=',
            '!' => '\\!',
            '<' => '\\<',
            '≤' => '\\≤',
            '>' => '\\>',
            '≥' => '\\≥',
            '…' => '\\…',
            '//' => '\\//',
            '?' => '\\?',
            '@' => '\\@',
            '#' => '\\#',
            '*' => '\\*',
            '"' => '\\"',
            '~' => '\\~',
        ]);
    }

    private function parseRecord(SimpleXMLElement $recordData, array $metadata): array
    {
        $record = $this->createRecord($recordData, $metadata);

        if (isset($recordData->relatedset)) {
            foreach ($recordData->relatedset as $relatedSetData) {
                $relatedSetName = (string) $relatedSetData['table'];
                $record[$relatedSetName] = [];

                foreach ($relatedSetData->record as $relatedSetRecordData) {
                    $record[$relatedSetName][] = $this->createRecord(
                        $relatedSetRecordData,
                        $metadata,
                        strlen($relatedSetName) + 2
                    );
                }
            }
        }

        return $record;
    }

    private function createRecord(SimpleXMLElement $recordData, array $metadata, int $prefixLength = 0): array
    {
        $record = [
            'record-id' => (int) $recordData['record-id'],
            'mod-id' => (int) $recordData['mod-id'],
        ];

        foreach ($recordData->field as $fieldData) {
            $fieldName = (string) $fieldData['name'];
            $localName = substr($fieldName, $prefixLength);

            if (! $metadata[$fieldName]['repeatable']) {
                $record[$localName] = $metadata[$fieldName]['transformer']((string) $fieldData->data);

                continue;
            }

            $record[$localName] = [];

            foreach ($fieldData->data as $data) {
                $record[$localName][] = $metadata[$fieldName]['transformer']((string) $data);
            }
        }

        return $record;
    }

    private function parseMetadata(SimpleXMLElement $xml): array
    {
        $metadata = [];

        foreach ($xml->{'field-definition'} as $fieldDefinition) {
            $metadata[(string) $fieldDefinition['name']] = [
                'repeatable' => ((int) $fieldDefinition['max-repeat']) > 1,
                'transformer' => $this->getFieldTransformer($fieldDefinition),
            ];
        }

        foreach ($xml->{'relatedset-definition'} as $relatedSetDefinition) {
            $metadata += $this->parseMetadata($relatedSetDefinition);
        }

        return $metadata;
    }

    private function getFieldTransformer(SimpleXMLElement $fieldDefinition): callable
    {
        $type = (string) $fieldDefinition['result'];

        if ($type === 'unknown') {
            throw UnknownFieldException::fromUnknownField();
        }

        if (! array_key_exists($type, $this->transformers)) {
            throw ParseException::fromInvalidFieldType(
                (string) $fieldDefinition['name'],
                (string) $fieldDefinition['result']
            );
        }

        return $this->transformers[$type];
    }

    private function initializeTransformers(DateTimeZone $serverTimeZone)
    {
        $this->transformers = [
            'text' => new TextTransformer(),
            'number' => new NumberTransformer(),
            'date' => new DateTransformer(),
            'time' => new TimeTransformer(),
            'timestamp' => new DateTimeTransformer($serverTimeZone),
            'container' => new ContainerTransformer($this->connection),
        ];
    }
}
