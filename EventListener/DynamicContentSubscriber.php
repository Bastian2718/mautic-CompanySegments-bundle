<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles evaluation of company_segments filters in Dynamic Web Content.
 */
class DynamicContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanySegmentRepository $companySegmentRepository,
        private CompanyLeadRepository $companyLeadRepository,
        private Config $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE => ['onContactFilterEvaluate', 10],
        ];
    }

    public function onContactFilterEvaluate(ContactFiltersEvaluateEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        foreach ($event->getFilters() as $filter) {
            \assert(is_array($filter));
            if (CompanySegmentModel::PROPERTIES_FIELD === ($filter['field'] ?? null)
                && CompanySegmentModel::PROPERTIES_FIELD === ($filter['object'] ?? null)) {
                $operator = $filter['operator'] ?? '';
                \assert(is_string($operator));
                $filterValue = $filter['filter'] ?? [];
                \assert(is_array($filterValue));
                $segmentIds = array_map('intval', $filterValue);

                $event->setIsMatched(
                    $this->isContactCompanySegmentRelationshipValid(
                        $event->getContact(),
                        $operator,
                        $segmentIds
                    )
                );
                $event->setIsEvaluated(true);

                return;
            }
        }
    }

    /**
     * @param int[] $segmentIds
     */
    private function isContactCompanySegmentRelationshipValid(
        Lead $contact,
        string $operator,
        array $segmentIds = [],
    ): bool {
        // Get the primary company ID directly from repository (avoid lazy-loading issues)
        try {
            $primaryCompany = $this->companyLeadRepository->getPrimaryCompanyByLeadId($contact->getId());
            \assert(isset($primaryCompany['id']) && is_numeric($primaryCompany['id']));
            $companyId = (int) $primaryCompany['id'];
        } catch (PrimaryCompanyNotFoundException) {
            return match ($operator) {
                OperatorOptions::EMPTY => true,
                default                => false,
            };
        }

        return match ($operator) {
            OperatorOptions::EMPTY     => !$this->companySegmentRepository->isCompanyInAnySegment($companyId),
            OperatorOptions::NOT_EMPTY => $this->companySegmentRepository->isCompanyInAnySegment($companyId),
            OperatorOptions::IN        => $this->companySegmentRepository->isCompanyInSegments($companyId, $segmentIds),
            OperatorOptions::NOT_IN    => $this->companySegmentRepository->isNotCompanyInSegments($companyId, $segmentIds),
            default                    => throw new \InvalidArgumentException(sprintf("Unexpected operator '%s'", $operator)),
        };
    }
}
