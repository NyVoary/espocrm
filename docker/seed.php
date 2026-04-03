<?php
/**
 * Seed script — insère des données de test dans EspoCRM.
 *
 * Usage (depuis le container app) :
 *   docker-compose exec app php /var/www/html/docker/seed.php
 *
 * Options :
 *   --reset    Supprime toutes les données existantes avant d'insérer
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$reset = in_array('--reset', $argv ?? []);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function log_info(string $msg): void {
    echo "[seed] $msg\n";
}

function create(EntityManager $em, string $type, array $data): string {
    $entity = $em->createEntity($type, $data);
    return $entity->getId();
}

// ─── Reset ───────────────────────────────────────────────────────────────────

if ($reset) {
    log_info("--reset : suppression des données existantes...");

    foreach (['Task', 'Opportunity', 'Call', 'Meeting', 'Lead', 'Contact', 'Account'] as $type) {
        $collection = $em->getRDBRepository($type)->find();
        $count = 0;

        foreach ($collection as $entity) {
            $em->removeEntity($entity);
            $count++;
        }

        log_info("  $type : $count enregistrement(s) supprimé(s).");
    }
}

// ─── Comptes (Accounts) ──────────────────────────────────────────────────────

log_info("Création des comptes...");

$accounts = [
    ['name' => 'Rakoto Technology SA',     'industry' => 'Technology',    'type' => 'Customer',  'website' => 'https://rakoto-tech.mg',  'phoneNumber' => '+261 20 22 00001', 'emailAddress' => 'contact@rakoto-tech.mg'],
    ['name' => 'Rasoa Import Export',      'industry' => 'Transportation', 'type' => 'Partner',   'website' => 'https://rasoa-import.mg', 'phoneNumber' => '+261 20 22 00002', 'emailAddress' => 'info@rasoa-import.mg'],
    ['name' => 'Mialy Consulting Group',   'industry' => 'Finance',        'type' => 'Customer',  'website' => 'https://mialy-consulting.mg', 'phoneNumber' => '+261 20 22 00003', 'emailAddress' => 'contact@mialy-consulting.mg'],
    ['name' => 'Andry Construction',       'industry' => 'Construction',   'type' => 'Customer',  'website' => 'https://andry-construction.mg', 'phoneNumber' => '+261 20 22 00004', 'emailAddress' => 'info@andry-construction.mg'],
    ['name' => 'Haja Agro Madagascar',     'industry' => 'Agriculture',    'type' => 'Partner',   'website' => 'https://haja-agro.mg',    'phoneNumber' => '+261 20 22 00005', 'emailAddress' => 'contact@haja-agro.mg'],
    ['name' => 'Fidy Media & Com',         'industry' => 'Entertainment',  'type' => 'Customer',  'website' => 'https://fidy-media.mg',   'phoneNumber' => '+261 20 22 00006', 'emailAddress' => 'hello@fidy-media.mg'],
    ['name' => 'Nivo Santé Clinic',        'industry' => 'Healthcare',     'type' => 'Customer',  'website' => 'https://nivo-sante.mg',   'phoneNumber' => '+261 20 22 00007', 'emailAddress' => 'rdv@nivo-sante.mg'],
    ['name' => 'Tojo Energy Solutions',    'industry' => 'Energy',         'type' => 'Reseller',  'website' => 'https://tojo-energy.mg',  'phoneNumber' => '+261 20 22 00008', 'emailAddress' => 'info@tojo-energy.mg'],
];

$accountIds = [];
foreach ($accounts as $data) {
    $accountIds[] = create($em, 'Account', $data);
}

log_info("  " . count($accountIds) . " comptes créés.");

// ─── Contacts ────────────────────────────────────────────────────────────────

log_info("Création des contacts...");

$contacts = [
    ['firstName' => 'Jean',     'lastName' => 'Rakoto',       'title' => 'Directeur Général',    'emailAddress' => 'jean.rakoto@rakoto-tech.mg',       'phoneNumber' => '+261 34 00 00001', 'accountId' => $accountIds[0]],
    ['firstName' => 'Soa',      'lastName' => 'Rasoa',        'title' => 'Responsable Import',   'emailAddress' => 'soa.rasoa@rasoa-import.mg',        'phoneNumber' => '+261 34 00 00002', 'accountId' => $accountIds[1]],
    ['firstName' => 'Mialy',    'lastName' => 'Andriantsoa',  'title' => 'Directrice Finance',   'emailAddress' => 'mialy@mialy-consulting.mg',        'phoneNumber' => '+261 34 00 00003', 'accountId' => $accountIds[2]],
    ['firstName' => 'Andry',    'lastName' => 'Ramiandrisoa', 'title' => 'Chef de projet',       'emailAddress' => 'andry@andry-construction.mg',     'phoneNumber' => '+261 34 00 00004', 'accountId' => $accountIds[3]],
    ['firstName' => 'Haja',     'lastName' => 'Rakotondrabe', 'title' => 'Gérant',               'emailAddress' => 'haja@haja-agro.mg',               'phoneNumber' => '+261 34 00 00005', 'accountId' => $accountIds[4]],
    ['firstName' => 'Fidy',     'lastName' => 'Randria',      'title' => 'Directeur Créatif',    'emailAddress' => 'fidy@fidy-media.mg',              'phoneNumber' => '+261 34 00 00006', 'accountId' => $accountIds[5]],
    ['firstName' => 'Nivo',     'lastName' => 'Rajaonarison', 'title' => 'Médecin Chef',         'emailAddress' => 'nivo@nivo-sante.mg',              'phoneNumber' => '+261 34 00 00007', 'accountId' => $accountIds[6]],
    ['firstName' => 'Tojo',     'lastName' => 'Rabemananjara','title' => 'Ingénieur',            'emailAddress' => 'tojo@tojo-energy.mg',             'phoneNumber' => '+261 34 00 00008', 'accountId' => $accountIds[7]],
    ['firstName' => 'Lalaina',  'lastName' => 'Rakotobe',     'title' => 'Responsable Achats',   'emailAddress' => 'lalaina@rakoto-tech.mg',          'phoneNumber' => '+261 34 00 00009', 'accountId' => $accountIds[0]],
    ['firstName' => 'Volatiana','lastName' => 'Razafindrakoto','title' => 'Comptable',           'emailAddress' => 'vola@mialy-consulting.mg',        'phoneNumber' => '+261 34 00 00010', 'accountId' => $accountIds[2]],
];

$contactIds = [];
foreach ($contacts as $data) {
    $contactIds[] = create($em, 'Contact', $data);
}

log_info("  " . count($contactIds) . " contacts créés.");

// ─── Leads ───────────────────────────────────────────────────────────────────

log_info("Création des leads...");

$leads = [
    ['firstName' => 'Ravo',    'lastName' => 'Randriamahefa', 'emailAddress' => 'ravo@example.mg',   'accountName' => 'Ravo Telecom',       'status' => 'New',         'source' => 'Web Site',    'industry' => 'Technology'],
    ['firstName' => 'Lanto',   'lastName' => 'Razafimahefa',  'emailAddress' => 'lanto@example.mg',  'accountName' => 'Lanto Services',     'status' => 'Assigned',    'source' => 'Call',        'industry' => 'Finance'],
    ['firstName' => 'Mahefa',  'lastName' => 'Andrianary',    'emailAddress' => 'mahefa@example.mg', 'accountName' => 'Mahefa BTP',         'status' => 'In Process',  'source' => 'Email',       'industry' => 'Construction'],
    ['firstName' => 'Rina',    'lastName' => 'Rasolofo',      'emailAddress' => 'rina@example.mg',   'accountName' => 'Rina Agro',          'status' => 'New',         'source' => 'Word-of-mouth','industry' => 'Agriculture'],
    ['firstName' => 'Tsiry',   'lastName' => 'Ratovo',        'emailAddress' => 'tsiry@example.mg',  'accountName' => 'Tsiry Import',       'status' => 'Recycled',    'source' => 'Partner',     'industry' => 'Transportation'],
    ['firstName' => 'Zo',      'lastName' => 'Andriamboavonjy','emailAddress' => 'zo@example.mg',   'accountName' => 'Zo Media',           'status' => 'New',         'source' => 'Web Site',    'industry' => 'Entertainment'],
];

$leadIds = [];
foreach ($leads as $data) {
    $leadIds[] = create($em, 'Lead', $data);
}

log_info("  " . count($leadIds) . " leads créés.");

// ─── Opportunités ─────────────────────────────────────────────────────────────

log_info("Création des opportunités...");

$opportunities = [
    ['name' => 'Déploiement ERP Rakoto Tech',      'stage' => 'Prospecting',      'amount' => 25000,  'probability' => 20,  'closeDate' => '2026-07-01', 'accountId' => $accountIds[0]],
    ['name' => 'Refonte SI Rasoa Import',          'stage' => 'Qualification',    'amount' => 18000,  'probability' => 40,  'closeDate' => '2026-06-15', 'accountId' => $accountIds[1]],
    ['name' => 'Audit Financier Mialy Consulting', 'stage' => 'Needs Analysis',   'amount' => 8000,   'probability' => 60,  'closeDate' => '2026-05-30', 'accountId' => $accountIds[2]],
    ['name' => 'Logiciel Gestion Chantiers Andry', 'stage' => 'Value Proposition','amount' => 12000,  'probability' => 60,  'closeDate' => '2026-05-15', 'accountId' => $accountIds[3]],
    ['name' => 'Plateforme E-commerce Haja Agro',  'stage' => 'Id. Decision Makers','amount' => 9500, 'probability' => 40,  'closeDate' => '2026-08-01', 'accountId' => $accountIds[4]],
    ['name' => 'App Mobile Fidy Media',            'stage' => 'Perception Analysis','amount' => 15000,'probability' => 60,  'closeDate' => '2026-06-01', 'accountId' => $accountIds[5]],
    ['name' => 'Système Rendez-vous Nivo Santé',   'stage' => 'Proposal/Price Quote','amount' => 7000,'probability' => 75,  'closeDate' => '2026-04-30', 'accountId' => $accountIds[6]],
    ['name' => 'Monitoring Énergie Tojo',          'stage' => 'Negotiation/Review','amount' => 20000, 'probability' => 90,  'closeDate' => '2026-04-20', 'accountId' => $accountIds[7]],
    ['name' => 'Renouvellement Contrat Rakoto',    'stage' => 'Closed Won',        'amount' => 5000,  'probability' => 100, 'closeDate' => '2026-03-01', 'accountId' => $accountIds[0]],
    ['name' => 'Migration Cloud Mialy',            'stage' => 'Closed Lost',       'amount' => 11000, 'probability' => 0,   'closeDate' => '2026-02-01', 'accountId' => $accountIds[2]],
];

$opportunityIds = [];
foreach ($opportunities as $data) {
    $opportunityIds[] = create($em, 'Opportunity', $data);
}

log_info("  " . count($opportunityIds) . " opportunités créées.");

// ─── Réunions ────────────────────────────────────────────────────────────────

log_info("Création des réunions...");

$meetings = [
    ['name' => 'Demo ERP Rakoto Tech',        'status' => 'Planned',  'dateStart' => '2026-04-10 09:00:00', 'dateEnd' => '2026-04-10 10:00:00', 'description' => 'Présentation de la solution ERP.'],
    ['name' => 'Kick-off Tojo Energy',        'status' => 'Planned',  'dateStart' => '2026-04-12 14:00:00', 'dateEnd' => '2026-04-12 15:30:00', 'description' => 'Lancement officiel du projet monitoring.'],
    ['name' => 'Revue Proposition Nivo Santé','status' => 'Held',     'dateStart' => '2026-03-25 10:00:00', 'dateEnd' => '2026-03-25 11:00:00', 'description' => 'Revue de la proposition commerciale.'],
    ['name' => 'Comité de pilotage Andry',    'status' => 'Held',     'dateStart' => '2026-03-20 09:00:00', 'dateEnd' => '2026-03-20 10:30:00', 'description' => 'Point avancement projet logiciel chantiers.'],
    ['name' => 'Atelier Fidy Media App',      'status' => 'Not Held', 'dateStart' => '2026-03-15 14:00:00', 'dateEnd' => '2026-03-15 16:00:00', 'description' => 'Atelier de définition des fonctionnalités.'],
];

foreach ($meetings as $data) {
    create($em, 'Meeting', $data);
}

log_info("  " . count($meetings) . " réunions créées.");

// ─── Appels ──────────────────────────────────────────────────────────────────

log_info("Création des appels...");

$calls = [
    ['name' => 'Qualification Ravo Telecom',  'status' => 'Planned',  'direction' => 'Outbound', 'dateStart' => '2026-04-08 11:00:00', 'duration' => 30],
    ['name' => 'Suivi devis Nivo Santé',      'status' => 'Held',     'direction' => 'Inbound',  'dateStart' => '2026-03-28 15:00:00', 'duration' => 20],
    ['name' => 'Négociation Tojo Energy',     'status' => 'Held',     'direction' => 'Outbound', 'dateStart' => '2026-03-22 10:00:00', 'duration' => 45],
    ['name' => 'Relance Lanto Services',      'status' => 'Not Held', 'direction' => 'Outbound', 'dateStart' => '2026-03-18 14:00:00', 'duration' => 15],
];

foreach ($calls as $data) {
    create($em, 'Call', $data);
}

log_info("  " . count($calls) . " appels créés.");

// ─── Tâches ──────────────────────────────────────────────────────────────────

log_info("Création des tâches...");

$tasks = [
    ['name' => 'Envoyer devis Nivo Santé',         'status' => 'Not Started', 'priority' => 'High',   'dateEnd' => '2026-04-08', 'assignedUserId' => null],
    ['name' => 'Préparer contrat Tojo Energy',     'status' => 'In Process',  'priority' => 'High',   'dateEnd' => '2026-04-15', 'assignedUserId' => null],
    ['name' => 'Appeler Ravo Telecom',             'status' => 'Not Started', 'priority' => 'Normal', 'dateEnd' => '2026-04-09', 'assignedUserId' => null],
    ['name' => 'Mettre à jour CRM Rakoto Tech',    'status' => 'In Process',  'priority' => 'Normal', 'dateEnd' => '2026-04-10', 'assignedUserId' => null],
    ['name' => 'Relancer Mahefa BTP',              'status' => 'Not Started', 'priority' => 'Low',    'dateEnd' => '2026-04-20', 'assignedUserId' => null],
    ['name' => 'Analyser besoins Fidy Media',      'status' => 'Completed',   'priority' => 'Normal', 'dateEnd' => '2026-03-30', 'assignedUserId' => null],
    ['name' => 'Valider spécifications Andry',     'status' => 'Completed',   'priority' => 'High',   'dateEnd' => '2026-03-25', 'assignedUserId' => null],
    ['name' => 'Créer compte demo Mialy Consulting','status' => 'Not Started','priority' => 'Low',    'dateEnd' => '2026-04-25', 'assignedUserId' => null],
];

foreach ($tasks as $data) {
    unset($data['assignedUserId']);
    create($em, 'Task', $data);
}

log_info("  " . count($tasks) . " tâches créées.");

// ─── Résumé ──────────────────────────────────────────────────────────────────

echo "\n";
log_info("Seed terminé avec succes !");
log_info("  Comptes       : " . count($accountIds));
log_info("  Contacts      : " . count($contactIds));
log_info("  Leads         : " . count($leadIds));
log_info("  Opportunités  : " . count($opportunityIds));
log_info("  Réunions      : " . count($meetings));
log_info("  Appels        : " . count($calls));
log_info("  Tâches        : " . count($tasks));
echo "\n";
