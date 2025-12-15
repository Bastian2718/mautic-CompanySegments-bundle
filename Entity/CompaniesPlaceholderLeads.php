<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;

class CompaniesPlaceholderLeads
{
    public const TABLE_NAME     = 'companies_placeholder_leads';
    public const RELATIONS_NAME = 'cspl';

    private Company $company;
    private Lead $lead;

    public static function loadMetadata(ORMClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CompaniesSegmentsRepository::class);

        $builder->createManyToOne('company', Company::class)
            ->isPrimaryKey()
            ->addJoinColumn('company_id', 'id', false, false, 'CASCADE')
            ->cascadeRefresh()
            ->build();

        $builder->createManyToOne('lead', Lead::class)
            ->addJoinColumn('lead_id', 'id', false, false, 'CASCADE')
            ->build();
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): void
    {
        $this->lead = $lead;
    }
}
