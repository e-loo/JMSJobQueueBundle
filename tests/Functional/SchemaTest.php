<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaValidator;

class SchemaTest extends BaseTestCase
{
    public function testSchemaIsValid(): void
    {
        $this->createClient();

        $schemaValidator = new SchemaValidator(self::$kernel->getContainer()->get('doctrine.orm.entity_manager'));
        $errors = $schemaValidator->validateMapping();

        $this->assertEmpty($errors, "Validation errors found: \n\n".var_export($errors, true));
    }
}
