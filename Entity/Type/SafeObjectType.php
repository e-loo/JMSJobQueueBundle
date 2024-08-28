<?php

namespace JMS\JobQueueBundle\Entity\Type;

use Doctrine\DBAL\Types\JsonType;

class SafeObjectType extends JsonType
{
    public function getSQLDeclaration(array $column, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return 'jms_job_safe_object';
    }
}
