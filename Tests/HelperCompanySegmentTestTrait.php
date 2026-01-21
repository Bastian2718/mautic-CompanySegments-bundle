<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignMember;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;

trait HelperCompanySegmentTestTrait
{
    /**
     * @param array<string, mixed> $properties
     */
    private function createEventModifyCompanySegment(
        string $name,
        string $type,
        array $properties = [],
        string $eventType = 'action',
        int $order =1,
        string $anchor = '',
        ?Event $parent = null,
    ): Event {
        $event = new Event();
        $event->setOrder($order);
        $event->setName($name);
        $event->setType($type);
        $event->setEventType($eventType);
        $event->setProperties($properties);
        if ('' !== $anchor) {
            $event->setDecisionPath($anchor);
        }
        if (null !== $parent) {
            $event->setParent($parent);
        }

        return $event;
    }

    private function addLeadInCampaign(Campaign $campaign, Lead $lead): CampaignMember
    {
        $campaignMember = new CampaignMember();
        $campaignMember->setLead($lead);
        $campaignMember->setCampaign($campaign);
        $campaignMember->setDateAdded(new \DateTime('-61 seconds'));

        return $campaignMember;
    }

    private function createLead(string $email, string $name='Joe'): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createCompany(string $companyName = 'Mauticcomp'): Company
    {
        $company = new Company();
        $company->setName($companyName);
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    /**
     * @param array<array<mixed>> $filters
     */
    private function createCompanySegment(
        string $name = 'Segment test',
        string $alias = 'segment-test',
        bool $isPublished = true,
        array $filters = [],
    ): CompanySegment {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        $companySegment->setDateAdded(new \DateTime());
        $companySegment->setDateModified(new \DateTime());
        if ([] !== $filters) {
            $companySegment->setFilters($filters);
        }
        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    private function addLeadToCompany(Lead $lead, Company $company, bool $isPrimary = false): void
    {
        $lead->setPrimaryCompany($company);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());
        $this->em->persist($lead);
        $this->em->flush();

        $companyModel  = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);
        $companyModel->addLeadToCompany($company, $lead);
    }

    private function addCompanyToCompanySegment(Company $company, CompanySegment $companySegment): void
    {
        $companiesSegments = new CompaniesSegments();
        $companiesSegments->setCompany($company);
        $companiesSegments->setCompanySegment($companySegment);
        $companiesSegments->setDateAdded(new \DateTime());
        $this->em->persist($companiesSegments);
        $this->em->flush();
    }

    /**
     * @param array<array<mixed>> $filters
     */
    private function createSegment(string $name, string $alias, array $filters = [], bool $isPublished = true): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setPublicName($name);
        $segment->setAlias($alias);
        $segment->setIsPublished($isPublished);
        $segment->setDateAdded(new \DateTime());
        $segment->setDateModified(new \DateTime());
        if ([] !== $filters) {
            $segment->setFilters($filters);
        }
        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    /**
     * @return string[]
     */
    private function createAjaxHeaders(): array
    {
        return [
            'HTTP_Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token'     => $this->getCsrfToken('mautic_ajax_post'),
        ];
    }

    private function loginAdminUser(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        assert($user instanceof User);
        $this->loginUser($user);
    }
}
