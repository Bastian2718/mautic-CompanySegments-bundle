<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeads;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesPlaceholderLeadsRepository;

class CompanySubscriber
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

        // 1) Gibt es bereits einen Primary Lead für diese Company?
        $primaryLead = $this->companiesPrimaryLeadsRepository
            ->getPrimaryLeadOfCompany($company->getId());

        if ($primaryLead instanceof Lead) {
            $this->updatePlaceholderLead($primaryLead, $company);

            return;
        }

        // company hat email und lead mit selber email existiert bereits --> update + createNewCompaniesPlaceholderLeadsEntry
        // company hat email und lead mit email existiert nicht --> create + createNewCompaniesPlaceholderLeadsEntry
        // company hat keine email --> createPlaceholderLead + createNewCompaniesPlaceholderLeadsEntry


        // 2) Kein Primary Lead: Prüfen, ob ein Lead mit gleicher E-Mail existiert
        $companyEmail = $company->getEmail();
        if (!empty($companyEmail)) {
            // Ohne E-Mail können wir keine Suche/Verknüpfung sinnvoll machen
            return;
        }

        /** @var Lead|null $leadWithSameEmail */
        $leadWithSameEmail = $this->entityManager
            ->getRepository(Lead::class)
            ->findOneBy(['email' => $companyEmail]);

        if ($leadWithSameEmail instanceof Lead) {
            // YES --> updatePlaceholderLead + createNewCompaniesPlaceholderLeadsEntry
            $this->updatePlaceholderLead($leadWithSameEmail, $company);
            $this->createCompaniesPlaceholderLeadsEntry($company, $leadWithSameEmail);

            return;
        }

        // NO --> createPlaceholderLead + createNewCompaniesPlaceholderLeadsEntry
        $placeholderLead = $this->createPlaceholderLead($company);
        $this->createCompaniesPlaceholderLeadsEntry($company, $placeholderLead);
    }

    /**
     * Aktualisiert einen vorhandenen Lead anhand der Company-Daten (Platzhalter-Logik).
     */
    private function updatePlaceholderLead(Lead $lead, Company $company): void
    {
        $companyEmail = $company->getEmail();
        if (!empty($companyEmail) && $lead->getEmail() !== $companyEmail) {
            $lead->setEmail($companyEmail);
        }

        // Optional: Company-Name als "company"-Feld des Leads setzen, falls leer
        if ($company->getName() && !$lead->getCompany()) {
            $lead->setCompany($company->getName());
        }

        $this->entityManager->persist($lead);
        $this->entityManager->flush();
    }

    /**
     * Erstellt einen neuen Platzhalter-Lead auf Basis der Company.
     */
    private function createPlaceholderLead(Company $company): Lead
    {
        $lead = new Lead();

        if ($company->getEmail()) {
            $lead->setEmail($company->getEmail());
        }

        if ($company->getName()) {
            // Company-Name als "company" im Lead hinterlegen (wird z. B. als Identifier genutzt)
            $lead->setCompany($company->getName());
        }

        $this->entityManager->persist($lead);
        $this->entityManager->flush();

        return $lead;
    }

    /**
     * Legt einen Eintrag in der Tabelle companies_placeholder_leads an.
     */
    private function createCompaniesPlaceholderLeadsEntry(Company $company, Lead $lead): void
    {
        $relation = new CompaniesPlaceholderLeads();
        $relation->setCompany($company);
        $relation->setLead($lead);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
    }
}