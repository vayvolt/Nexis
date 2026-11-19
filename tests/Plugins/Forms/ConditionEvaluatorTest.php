<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugins\Forms;

use Nexis\Plugins\Forms\ConditionEvaluator;
use Nexis\Plugins\Forms\FormField;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/plugins/nexis/forms/src/FormField.php';
require_once dirname(__DIR__, 3) . '/plugins/nexis/forms/src/ConditionEvaluator.php';
require_once dirname(__DIR__, 3) . '/plugins/nexis/forms/src/FormsBuilderController.php';

final class ConditionEvaluatorTest extends TestCase
{
    public function testHiddenRequiredFieldIsNotValidated(): void
    {
        $fields = [
            new FormField('kind', 'Kind', 'select', true, [
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ]),
            new FormField('detail', 'Detail', 'text', true, [], '', [
                'field' => 'kind',
                'op' => 'eq',
                'value' => 'b',
            ]),
        ];

        self::assertSame([], ConditionEvaluator::validate($fields, ['kind' => 'a', 'detail' => '']));
        self::assertSame(['detail'], ConditionEvaluator::validate($fields, ['kind' => 'b', 'detail' => '']));
        self::assertSame([], ConditionEvaluator::validate($fields, ['kind' => 'b', 'detail' => 'ok']));
    }

    public function testOpsEmptyNeqNotEmpty(): void
    {
        $empty = new FormField('x', 'X', 'text', false, [], '', [
            'field' => 'dep', 'op' => 'empty', 'value' => '',
        ]);
        $notEmpty = new FormField('y', 'Y', 'text', false, [], '', [
            'field' => 'dep', 'op' => 'not_empty', 'value' => '',
        ]);
        $neq = new FormField('z', 'Z', 'text', false, [], '', [
            'field' => 'dep', 'op' => 'neq', 'value' => 'no',
        ]);

        self::assertTrue(ConditionEvaluator::isVisible($empty, ['dep' => '']));
        self::assertFalse(ConditionEvaluator::isVisible($empty, ['dep' => 'x']));
        self::assertTrue(ConditionEvaluator::isVisible($notEmpty, ['dep' => 'x']));
        self::assertFalse(ConditionEvaluator::isVisible($notEmpty, ['dep' => '']));
        self::assertTrue(ConditionEvaluator::isVisible($neq, ['dep' => 'yes']));
        self::assertFalse(ConditionEvaluator::isVisible($neq, ['dep' => 'no']));
    }

    public function testEmailAndSelectValidation(): void
    {
        $fields = [
            new FormField('email', 'E-Mail', 'email', true),
            new FormField('choice', 'Choice', 'select', true, [
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ]),
        ];

        self::assertSame(['email'], ConditionEvaluator::validate($fields, ['email' => 'nope', 'choice' => 'a']));
        self::assertSame(['choice'], ConditionEvaluator::validate($fields, ['email' => 'a@b.c', 'choice' => 'x']));
        self::assertSame([], ConditionEvaluator::validate($fields, ['email' => 'a@b.c', 'choice' => 'b']));
    }

    public function testParseOptionsAndDanglingConditions(): void
    {
        $options = \Nexis\Plugins\Forms\FormsBuilderController::parseOptions("ja|Ja\nnein\n");
        self::assertSame([
            ['value' => 'ja', 'label' => 'Ja'],
            ['value' => 'nein', 'label' => 'nein'],
        ], $options);

        $fields = FormField::listFromArray([
            ['key' => 'a', 'label' => 'A', 'type' => 'text'],
            ['key' => 'b', 'label' => 'B', 'type' => 'text', 'visibleWhen' => ['field' => 'missing', 'op' => 'eq', 'value' => '1']],
            ['key' => 'c', 'label' => 'C', 'type' => 'text', 'visibleWhen' => ['field' => 'a', 'op' => 'eq', 'value' => '1']],
        ]);
        self::assertNull($fields[1]->visibleWhen);
        self::assertSame('a', $fields[2]->visibleWhen['field'] ?? null);
    }
}
