<?php

namespace theodorejb\Phaster;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testPropListToPropMap(): void
    {
        $getValue = fn(array $_row): string => '';

        try {
            $prop = new Prop('test', 'col', false, true, '', null, false, $getValue, ['test']);
            Helpers::propListToPropMap([$prop]);
            $this->fail('Failed to throw exception for Prop that depends on itself');
        } catch (\Exception $e) {
            $this->assertSame("test property cannot depend on itself", $e->getMessage());
        }

        try {
            $prop = new Prop('isBillable', 'billable', false, true, '', null, false, $getValue, ['notAProp']);
            Helpers::propListToPropMap([$prop]);
            $this->fail('Failed to throw exception for invalid dependsOn key value');
        } catch (\Exception $e) {
            $this->assertSame("Invalid dependsOn value 'notAProp' on isBillable property", $e->getMessage());
        }
    }
}
