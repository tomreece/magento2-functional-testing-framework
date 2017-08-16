<?php

namespace Magento\AcceptanceTestFramework\DataGenerator\Handlers;

use Magento\AcceptanceTestFramework\DataGenerator\Objects\EntityDataObject;
use Magento\AcceptanceTestFramework\DataGenerator\Parsers\DataProfileSchemaParser;
use Magento\AcceptanceTestFramework\ObjectManager\ObjectHandlerInterface;
use Magento\AcceptanceTestFramework\ObjectManagerFactory;

class DataObjectHandler implements ObjectHandlerInterface
{
    /**
     * @var DataObjectHandler $DATA_OBJECT_HANDLER
     */
    private static $DATA_OBJECT_HANDLER;

    /**
     * @var array $arrayData
     */
    private $arrayData = [];
    /**
     * @var array $data
     */
    private $data = [];

    const ENV_DATA_OBJECT_NAME = '_ENV';

    const ENTITY_DATA = 'entity';
    const ENTITY_DATA_NAME = 'name';
    const ENTITY_DATA_TYPE = 'type';

    const DATA_VALUES = 'data';
    const DATA_ELEMENT_KEY = 'key';
    const DATA_ELEMENT_VALUE = 'value';

    const ARRAY_VALUES = 'array';
    const ARRAY_ELEMENT_KEY = 'key';
    const ARRAY_ELEMENT_ITEM = 'item';
    const ARRAY_ELEMENT_ITEM_VALUE = 'value';

    const REQUIRED_ENTITY = 'required-entity';
    const REQUIRED_ENTITY_TYPE = 'type';
    const REQUIRED_ENTITY_VALUE = 'value';

    /**
     * Singleton method to retrieve instance of DataArrayProcessor
     * @return DataObjectHandler
     * @throws \Exception
     */
    public static function getInstance()
    {
        if (!self::$DATA_OBJECT_HANDLER) {
            $entityParser = ObjectManagerFactory::getObjectManager()->create(DataProfileSchemaParser::class);
            $entityParsedData = $entityParser->readDataProfiles();

            if (!$entityParsedData) {
                throw new \Exception("No entities could be parsed from xml definitions");
            }

            self::$DATA_OBJECT_HANDLER = new DataObjectHandler($entityParsedData);
        }

        return self::$DATA_OBJECT_HANDLER;
    }

    /**
     * DataArrayProcessor constructor.
     * @constructor
     * @param array $arrayData
     */
    private function __construct($arrayData)
    {
        $this->arrayData = $arrayData;
    }

    /**
     * Retrieves the object representation of data represented in data.xml
     * @param string $entityName
     * @return EntityDataObject | null
     */
    public function getObject($entityName)
    {
        if (array_key_exists($entityName, $this->getAllObjects())) {
            return $this->getAllObjects()[$entityName];
        }

        return null;
    }

    /**
     * Retrieves all object representations of all data represented in data.xml
     * @return array
     */
    public function getAllObjects()
    {
        if (!$this->data) {
            $this->parseEnvVariables();
            $this->parseDataEntities();
        }

        return $this->data;
    }

    /**
     * Adds all .env variables defined in the PROJECT_ROOT as EntityDataObjects. This is to allow resolution
     * of these variables when referenced in a cest.
     * @return void
     */
    private function parseEnvVariables()
    {
        $envFilename = PROJECT_ROOT . '/.env';
        if (file_exists($envFilename)) {
            $envData = [];
            $envFile = file($envFilename);
            foreach ($envFile as $entry) {
                $params = explode("=", $entry);
                if (count($params) != 2) {
                    continue;
                }
                $envData[strtolower(trim($params[0]))] = trim($params[1]);
            }
            $envDataObject = new EntityDataObject(self::ENV_DATA_OBJECT_NAME, 'environment', $envData, null);
            $this->data[$envDataObject->getName()] = $envDataObject;
        }
    }

    /**
     * Parses array output of parses into EntityDataObjects.
     * @return void
     */
    private function parseDataEntities()
    {
        $entities = $this->arrayData;

        foreach ($entities[DataObjectHandler::ENTITY_DATA] as $entityName => $entity) {
            $entityType = $entity[DataObjectHandler::ENTITY_DATA_TYPE];

            $dataValues = [];
            $linkedEntities = [];
            $arrayValues = [];

            if (array_key_exists(DataObjectHandler::DATA_VALUES, $entity)) {
                foreach ($entity[DataObjectHandler::DATA_VALUES] as $dataElement) {
                    $dataElementKey = strtolower($dataElement[DataObjectHandler::DATA_ELEMENT_KEY]);
                    $dataElementValue = $dataElement[DataObjectHandler::DATA_ELEMENT_VALUE];

                    $dataValues[$dataElementKey] = $dataElementValue;
                }
                unset($dataElement);
            }

            if (array_key_exists(DataObjectHandler::REQUIRED_ENTITY, $entity)) {
                foreach ($entity[DataObjectHandler::REQUIRED_ENTITY] as $linkedEntity) {
                    $linkedEntityName = $linkedEntity[DataObjectHandler::REQUIRED_ENTITY_VALUE];
                    $linkedEntityType = $linkedEntity[DataObjectHandler::REQUIRED_ENTITY_TYPE];

                    $linkedEntities[$linkedEntityName] = $linkedEntityType;
                }
                unset($linkedEntity);
            }

            if (array_key_exists(DataObjectHandler::ARRAY_VALUES, $entity)) {
                foreach ($entity[DataObjectHandler::ARRAY_VALUES] as $arrayElement) {
                    $arrayKey = $arrayElement[DataObjectHandler::ARRAY_ELEMENT_KEY];
                    foreach ($arrayElement[DataObjectHandler::ARRAY_ELEMENT_ITEM] as $arrayValue) {
                        $arrayValues[] = $arrayValue[DataObjectHandler::ARRAY_ELEMENT_ITEM_VALUE];
                    }

                    $dataValues[$arrayKey] = $arrayValues;
                }
            }

            $entityDataObject = new EntityDataObject(
                $entityName,
                $entityType,
                $dataValues,
                $linkedEntities
            );

            $this->data[$entityDataObject->getName()] = $entityDataObject;

        }
        unset($entityName);
        unset($entity);
    }
}
