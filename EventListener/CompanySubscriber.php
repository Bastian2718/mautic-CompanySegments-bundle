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
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompaniesPlaceholderLeadsRepository $companiesPlaceholderLeadsRepository,
        private LeadRepository $leadRepository,
        private EntityManagerInterface $entityManager,
        private LeadModel $leadModel,
        private Config $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_POST_SAVE  => ['onCompanyPostSave', 0],
            LeadEvents::COMPANY_PRE_DELETE => ['onCompanyPreDelete', 0],
        ];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
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

    private function createPlaceholderLead(Company $company, ?string $email): Lead
    {
        $lead = $this->leadModel->getEntity();
        \assert($lead instanceof Lead);

        return $this->updatePlaceholderLead($lead, $company, $email);
    }

    private function updatePlaceholderLead(Lead $lead, Company $company, ?string $email = null): Lead
    {
        $lead->setFirstname($company->getName());
        $lead->setLastname('[PLACEHOLDER]');
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
