<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeadsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompaniesPlaceholderLeadsRepository $companiesPrimaryLeadsRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_POST_SAVE => ['onCompanyPostSave', 0],
        ];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        $company = $event->getCompany();
        if($company->getName() === null) {
            return;
        }

        $primaryLead = $this->companiesPrimaryLeadsRepository
            ->getPrimaryLeadOfCompany($company->getId());

        if ($primaryLead instanceof Lead) {
            $this->updatePlaceholderLead($primaryLead, $company);
            return;
        }


        //get relevant company email methode
        $companyEmail = $company->getEmail();
        if (!empty($companyEmail)) {
            /** @var Lead|null $leadWithSameEmail */
            $leadWithSameEmail = $this->entityManager
                ->getRepository(Lead::class)
                ->findOneBy(['email' => $companyEmail]);

            if ($leadWithSameEmail instanceof Lead) {
                //companyEmail = companyemail mit +1 vor @
                $this->updatePlaceholderLead($leadWithSameEmail, $company);
                $this->createCompaniesPlaceholderLeadsEntry($company, $leadWithSameEmail);
                return;
            }

            $placeholderLead = $this->createPlaceholderLead($company);
            $this->createCompaniesPlaceholderLeadsEntry($company, $placeholderLead);
        }
    }

    private function createPlaceholderLead(Company $company): Lead
    {
        $lead = new Lead();
        return $this->updatePlaceholderLead($lead, $company);
    }

    private function updatePlaceholderLead(Lead $lead, Company $company): Lead
    {
        $lead->setFirstname($company->getName());
        $lead->setEmail($company->getEmail() ?? '');
        $lead->setAddress1($company->getAddress1() ?? '');
        $lead->setAddress2($company->getAddress2() ?? '');
        $lead->setCity($company->getCity() ?? '');
        $lead->setState($company->getState() ?? '');
        $lead->setCountry($company->getCountry() ?? '');
        $lead->setZipcode($company->getZipcode() ?? '');
        $lead->setPhone($company->getPhone() ?? '');

        $this->entityManager->persist($lead);
        $this->entityManager->flush();
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
}