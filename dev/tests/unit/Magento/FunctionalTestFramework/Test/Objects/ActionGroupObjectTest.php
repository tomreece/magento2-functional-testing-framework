<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace tests\unit\Magento\FunctionalTestFramework\Test\Objects;

use AspectMock\Test as AspectMock;
use Magento\FunctionalTestingFramework\DataGenerator\Handlers\DataObjectHandler;
use Magento\FunctionalTestingFramework\Exceptions\TestReferenceException;
use Magento\FunctionalTestingFramework\Page\Handlers\SectionObjectHandler;
use Magento\FunctionalTestingFramework\Page\Objects\ElementObject;
use Magento\FunctionalTestingFramework\Page\Objects\SectionObject;
use Magento\FunctionalTestingFramework\Test\Objects\ActionGroupObject;
use Magento\FunctionalTestingFramework\Test\Objects\ActionObject;
use PHPUnit\Framework\TestCase;
use tests\unit\Util\ActionGroupObjectBuilder;
use tests\unit\Util\EntityDataObjectBuilder;

class ActionGroupObjectTest extends TestCase
{
    const ACTION_GROUP_MERGE_KEY = 'testKey';

    /**
     * Tests a string literal in an action group
     */
    public function testGetStepsWithDefaultCase()
    {
        $entity = (new EntityDataObjectBuilder())
            ->withDataFields(['field1' => 'testValue'])
            ->build();
        $this->setEntityObjectHandlerReturn($entity);
        $actionGroupUnderTest = (new ActionGroupObjectBuilder())->build();
        $steps = $actionGroupUnderTest->getSteps(null, self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => 'literal']);
    }

    /**
     * Tests a data reference in an action group, replaced by the user
     */
    public function testGetStepsWithCustomArgs()
    {
        $this->setEntityObjectHandlerReturn(function ($entityName) {
            if ($entityName == "data2") {
                return (new EntityDataObjectBuilder())->withDataFields(['field2' => 'testValue2'])->build();
            }
        });

        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects([new ActionObject('action1', 'testAction', ['userInput' => '{{arg1.field2}}'])])
            ->build();

        $steps = $actionGroupUnderTest->getSteps(['arg1' => 'data2'], self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => 'testValue2']);
    }

    /**
     * Tests a data reference in an action group replaced with a persisted reference.
     */
    public function testGetStepsWithPersistedArgs()
    {
        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects([new ActionObject('action1', 'testAction', ['userInput' => '{{arg1.field2}}'])])
            ->build();

        $steps = $actionGroupUnderTest->getSteps(['arg1' => '$data3$'], self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => '$data3.field2$']);
    }

    /**
     * Tests a data reference in an action group replaced with a data.field reference.
     */
    public function testGetStepsWithNoFieldArg()
    {
        $this->setEntityObjectHandlerReturn(function ($entityName) {
            if ($entityName == "data2") {
                return (new EntityDataObjectBuilder())->withDataFields(['field2' => 'testValue2'])->build();
            }
        });

        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects([new ActionObject('action1', 'testAction', ['userInput' => '{{arg1}}'])])
            ->build();

        $steps = $actionGroupUnderTest->getSteps(['arg1' => 'data2.field2'], self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => 'testValue2']);
    }

    /**
     * Tests a data reference in an action group resolved with its default state.
     */
    public function testGetStepsWithNoArgs()
    {
        $this->setEntityObjectHandlerReturn(function ($entityName) {
            if ($entityName == "data1") {
                return (new EntityDataObjectBuilder())->withDataFields(['field1' => 'testValue'])->build();
            }
        });

        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects([new ActionObject('action1', 'testAction', ['userInput' => '{{data1.field1}}'])])
            ->build();

        $steps = $actionGroupUnderTest->getSteps(null, self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => 'testValue']);
    }

    /**
     * Tests a parameterized section reference in an action group resolved with user args.
     */
    public function testGetStepsWithParameterizedArg()
    {
        // mock the section object handler response
        $element = new ElementObject("element1", "textArea", ".selector {{var1}}", null, null, true);
        $section = new SectionObject("testSection", ["element1" => $element]);
        // bypass the private constructor
        $sectionInstance = AspectMock::double(SectionObjectHandler::class, ['getObject' => $section])->make();
        AspectMock::double(SectionObjectHandler::class, ['getInstance' => $sectionInstance]);

        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects(
                [new ActionObject('action1', 'testAction', ['selector' => '{{section1.element1(arg1.field1)}}'])]
            )
            ->build();

        $steps = $actionGroupUnderTest->getSteps(['arg1' => '$someData$'], self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['selector' => '.selector $someData.field1$']);
    }

    /**
     * Tests a data reference in an action group resolved with a persisted reference used in another function.
     */
    public function testGetStepsWithOuterScopePersistence()
    {
        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withActionObjects([new ActionObject('action1', 'testAction', ['userInput' => '{{arg1.field1}}'])])
            ->build();

        $steps = $actionGroupUnderTest->getSteps(['arg1' => '$$someData$$'], self::ACTION_GROUP_MERGE_KEY);
        $this->assertOnMergeKeyAndActionValue($steps, ['userInput' => '$$someData.field1$$']);
    }

    /**
     * Tests an action group with mismatching args.
     */
    public function testExceptionOnMissingActions()
    {
        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withArguments(['arg1' => null])
            ->build();

        $this->expectException(TestReferenceException::class);
        $this->expectExceptionMessageRegExp('/Argument\(s\) missed .* for actionGroup/');
        $actionGroupUnderTest->getSteps(['arg2' => 'data1'], self::ACTION_GROUP_MERGE_KEY);
    }

    /**
     * Tests an action group with missing args.
     */
    public function testExceptionOnMissingArguments()
    {
        $actionGroupUnderTest = (new ActionGroupObjectBuilder())
            ->withArguments(['arg1' => null])
            ->build();

        $this->expectException(TestReferenceException::class);
        $this->expectExceptionMessageRegExp('/Not enough arguments given for actionGroup .*/');
        $actionGroupUnderTest->getSteps(null, self::ACTION_GROUP_MERGE_KEY);
    }

    /**
     * This function takes a desired return for the EntityObjectHandler mock and performs set up of the mock for the
     * duration of a single test case.
     *
     * @param mixed $return
     * @return void
     */
    private function setEntityObjectHandlerReturn($return)
    {
        $instance = AspectMock::double(DataObjectHandler::class, ['getObject' => $return])
            ->make(); // bypass the private constructor
        AspectMock::double(DataObjectHandler::class, ['getInstance' => $instance]);
    }

    /**
     * Given a set of steps and an expected custom attribute value, this function performs a set of asserts to validate
     * information such as step key and step attribute value.
     *
     * @param array $actions
     * @param array $expectedValue
     * @param string $expectedMergeKey
     * @return void
     */
    private function assertOnMergeKeyAndActionValue($actions, $expectedValue, $expectedMergeKey = null)
    {
        $expectedMergeKey = $expectedMergeKey ??
            ActionGroupObjectBuilder::DEFAULT_ACTION_OBJECT_NAME . self::ACTION_GROUP_MERGE_KEY;
        $this->assertArrayHasKey($expectedMergeKey, $actions);

        $action = $actions[$expectedMergeKey];
        $this->assertEquals($expectedMergeKey, $action->getStepKey());
        $this->assertEquals($expectedValue, $action->getCustomActionAttributes());
    }
}
