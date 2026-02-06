<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeadsRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegmentsRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CompanySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompaniesPlaceholderLeadsRepository $companiesPlaceholderLeadsRepository,
        private LeadRepository $leadRepository,
        private EntityManagerInterface $entityManager,
        private LeadModel $leadModel,
        private Config $config,
        private CompanySegmentModel $companySegmentModel,
        private CompaniesSegmentsRepository $companiesSegmentsRepository,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_POST_SAVE  => [
                ['onCompanyPostSave', 0],
                ['onCompanyPostSaveSegments', -10],
            ],
            LeadEvents::COMPANY_PRE_DELETE => ['onCompanyPreDelete', 0],
        ];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->getCreatePlaceholderContact()) {
            return;
        }
        $company = $event->getCompany();
        if (null === $company->getName()) {
            return;
        }

        $primaryLead = $this->companiesPlaceholderLeadsRepository
            ->getPrimaryLeadOfCompany($company->getId());

        if ($primaryLead instanceof Lead) {
            $this->updatePlaceholderLead($primaryLead, $company);

            return;
        }

        $companyEmail    = $this->getEmailAddressForPlaceholderLead($company);
        $placeholderLead = $this->createPlaceholderLead($company, $companyEmail);
        $this->createCompaniesPlaceholderLeadsEntry($company, $placeholderLead);
    }

    public function onCompanyPostSaveSegments(CompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        // Check if this is a company form submission
        $companyData = $request->request->all('company');
        if (!isset($companyData['company_segments'])) {
            return;
        }

        $company = $event->getCompany();

        $selectedSegmentIds = $companyData['company_segments'];
        if (!is_array($selectedSegmentIds)) {
            $selectedSegmentIds = [];
        }
        $selectedSegmentIds = array_filter(array_map('intval', $selectedSegmentIds));

        $currentSegments   = $this->companiesSegmentsRepository->getByCompany($company);
        $currentSegmentIds = array_values(array_filter(
            array_map(fn ($cs): ?int => $cs->getCompanySegment()->getId(), $currentSegments)
        ));
        $segmentsToAdd    = array_values(array_diff($selectedSegmentIds, $currentSegmentIds));
        $segmentsToRemove = array_values(array_diff($currentSegmentIds, $selectedSegmentIds));

        if ([] !== $segmentsToAdd) {
            $this->companySegmentModel->addCompany($company, $segmentsToAdd, true);
        }

        if ([] !== $segmentsToRemove) {
            $this->companySegmentModel->removeCompany($company, $segmentsToRemove, true);
        }
    }

    private function createPlaceholderLead(Company $company, ?string $email): Lead
    {
        $lead = $this->leadModel->getEntity();
        \assert($lead instanceof Lead);

        return $this->updatePlaceholderLead($lead, $company, $email);
    }

    private function updatePlaceholderLead(Lead $lead, Company $company, ?string $email = null): Lead
    {
        $lead->setFirstname($company->getName());
        $lead->setEmail($email ?? $company->getEmail() ?? '');
        $lead->setAddress1($company->getAddress1() ?? '');
        $lead->setAddress2($company->getAddress2() ?? '');
        $lead->setCity($company->getCity() ?? '');
        $lead->setState($company->getState() ?? '');
        $lead->setCountry($company->getCountry() ?? '');
        $lead->setZipcode($company->getZipcode() ?? '');
        $lead->setPhone($company->getPhone() ?? '');
        $lead->addUpdatedField('fax', $company->getFieldValue('companyfax') ?? '');

        $this->entityManager->persist($lead);
        $this->entityManager->flush();
        $this->leadModel->addToCompany($lead, $company);

        return $lead;
    }

    private function createCompaniesPlaceholderLeadsEntry(Company $company, Lead $lead): void
    {
        $relation = new CompaniesPlaceholderLeads();
        $relation->setCompany($company);
        $relation->setLead($lead);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
    }

    private function getEmailAddressForPlaceholderLead(Company $company): ?string
    {
        $companyEmail = $company->getEmail();
        if (null === $companyEmail) {
            return null;
        }

        $leadWithSameEmail = $this->leadRepository->findOneBy(['email' => $companyEmail]);

        if ($leadWithSameEmail instanceof Lead) {
            $companyEmail = str_replace('@', '+1@', $companyEmail);
        }

        return $companyEmail;
    }

    public function onCompanyPreDelete(CompanyEvent $event): void
    {
        $company = $event->getCompany();

        $placeholderEntry = $this->companiesPlaceholderLeadsRepository
            ->findOneBy(['company' => $company->getId()]);

        if ($placeholderEntry instanceof CompaniesPlaceholderLeads) {
            $this->leadRepository->deleteEntity($placeholderEntry->getLead());
        }
        $this->entityManager->flush();
    }
}
