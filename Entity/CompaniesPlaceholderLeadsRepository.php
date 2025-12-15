<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\Lead;

/**
 * @extends CommonRepository<CompaniesPlaceholderLeads>
 *
 * @see \Mautic\LeadBundle\Entity\ListLeadRepository
 */
class CompaniesPlaceholderLeadsRepository extends CommonRepository
{
    public function getPrimaryLeadOfCompany(int $id): ?Lead
    {
        $result = $this->findOneBy([
            'company'  => $id,
        ]);

        return $result;
    }
}
