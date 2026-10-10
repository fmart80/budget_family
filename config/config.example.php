<?php
// Copiez ce fichier en config.php et renseignez vos accès (Plesk > Bases de données).
return [
    'app_name' => 'Budget Famille',
    'timezone' => 'Europe/Zurich',
    'currency' => 'CHF',

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'budget_famille',
        'user'     => 'budget_user',
        'password' => 'changez-moi',
    ],

    // Clé secrète demandée lors de l'installation (mettez une valeur longue et aléatoire).
    'setup_key' => 'changez-moi',

    // Taille maximale des pièces jointes (factures), en octets.
    'max_upload_size' => 10 * 1024 * 1024,

    // Chemin du cookie de session. Laisser null : il est déduit automatiquement de l'adresse
    // (ex. « /budget/ » si l'application est installée dans https://votre-domaine.ch/budget/).
    'cookie_path' => null,

    // Mettre à true uniquement pour diagnostiquer un problème (affiche les erreurs PHP).
    'debug' => false,
];
