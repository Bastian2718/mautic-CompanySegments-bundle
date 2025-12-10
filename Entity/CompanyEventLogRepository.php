<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyEventLog>
 *
 * @see \Mautic\LeadBundle\Entity\ListLeadRepository
 */
class CompanyEventLogRepository extends CommonRepository
{
    /**
     * Updates lead ID (e.g. after a company merge).
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     */
    public function updateCompany($fromCompanyId, $toCompanyId): void
    {
        $toCompanyId  = (int) $toCompanyId;
        $toCompanyId  = (string) $toCompanyId;
        $q            = $this->_em->getConnection()->createQueryBuilder();
        $preTableName = '';
        if (is_string(MAUTIC_TABLE_PREFIX)) {
            $preTableName = MAUTIC_TABLE_PREFIX;
        }
        $q->update($preTableName.'company_event_log')
            ->set('company_id', $toCompanyId)
            ->where('company_id = '.(int) $fromCompanyId)
            ->executeStatement();
    }

    /**
     * Defines default table alias for company_event_log table.
     */
    public function getTableAlias(): string
    {
        return 'cel';
    }
}
